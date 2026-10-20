<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\SurveyInstance;
use App\Models\User;
use App\Services\CloudinaryEvidenceStorage;
use App\Support\SurveyEvidenceCategories;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class SurveyEvidenceController extends Controller
{
    public function signUpload(Request $request, string $publicId, string $surveyPublicId, CloudinaryEvidenceStorage $cloudinary): JsonResponse
    {
        $organization = $this->organizationForSurveys($publicId);
        $survey = $this->authorizedSurvey($organization, $surveyPublicId, true);
        abort_if($survey->status === 'completed', 409, 'Completed surveys are read-only.');

        $validated = $request->validate([
            'category_key' => ['required', 'string', 'max:80'],
            'original_filename' => ['required', 'string', 'max:255'],
            'mime_type' => ['required', 'string', 'in:image/jpeg,image/png,image/webp'],
            'byte_size' => ['required', 'integer', 'min:1', 'max:15728640'],
            'caption' => ['nullable', 'string', 'max:2000'],
        ]);
        $categoryLabel = SurveyEvidenceCategories::ALL[$survey->survey_type_slug_snapshot][$validated['category_key']] ?? null;
        if ($categoryLabel === null) {
            throw ValidationException::withMessages(['category_key' => 'Choose an evidence category from this survey.']);
        }

        try {
            $timestamp = now()->timestamp;
            $folder = implode('/', [
                $cloudinary->folder(),
                $organization->public_id,
                $survey->public_id,
                $validated['category_key'],
            ]);
            $publicAssetId = $folder.'/'.\Illuminate\Support\Str::uuid();
            $context = 'survey_public_id='.$survey->public_id.'|category_key='.$validated['category_key'];
            $parameters = [
                'context' => $context,
                'overwrite' => false,
                'public_id' => $publicAssetId,
                'timestamp' => $timestamp,
                'type' => 'authenticated',
            ];

            return response()->json(['data' => [
                'upload_url' => 'https://api.cloudinary.com/v1_1/'.rawurlencode($cloudinary->cloudName()).'/image/upload',
                'cloud_name' => $cloudinary->cloudName(),
                'api_key' => $cloudinary->apiKey(),
                'timestamp' => $timestamp,
                'signature' => $cloudinary->uploadSignature($parameters),
                'public_id' => $publicAssetId,
                'type' => 'authenticated',
                'context' => $context,
                'category_key' => $validated['category_key'],
                'category_label' => $categoryLabel,
                'original_filename' => $validated['original_filename'],
                'mime_type' => $validated['mime_type'],
                'byte_size' => $validated['byte_size'],
                'caption' => $validated['caption'] ?? null,
            ]]);
        } catch (RuntimeException) {
            return response()->json(['message' => 'Secure image upload is not configured on the backend.'], 503);
        }
    }

    public function confirmUpload(Request $request, string $publicId, string $surveyPublicId, CloudinaryEvidenceStorage $cloudinary): JsonResponse
    {
        $organization = $this->organizationForSurveys($publicId);
        $survey = $this->authorizedSurvey($organization, $surveyPublicId, true);
        abort_if($survey->status === 'completed', 409, 'Completed surveys are read-only.');

        $validated = $request->validate([
            'category_key' => ['required', 'string', 'max:80'],
            'public_id' => ['required', 'string', 'max:255'],
            'original_filename' => ['required', 'string', 'max:255'],
            'mime_type' => ['required', 'string', 'in:image/jpeg,image/png,image/webp'],
            'caption' => ['nullable', 'string', 'max:2000'],
        ]);
        $categoryLabel = SurveyEvidenceCategories::ALL[$survey->survey_type_slug_snapshot][$validated['category_key']] ?? null;
        if ($categoryLabel === null) {
            throw ValidationException::withMessages(['category_key' => 'Choose an evidence category from this survey.']);
        }
        $expectedPrefix = implode('/', [$cloudinary->folder(), $organization->public_id, $survey->public_id, $validated['category_key']]).'/';
        if (! str_starts_with($validated['public_id'], $expectedPrefix)) {
            abort(422, 'The uploaded image does not belong to this survey category.');
        }

        try {
            $asset = $cloudinary->verifyAuthenticatedImage($validated['public_id']);
        } catch (Throwable) {
            return response()->json(['message' => 'Cloudinary could not verify the uploaded image.'], 422);
        }
        $format = strtolower((string) ($asset['format'] ?? ''));
        $mimeType = match ($format) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => null,
        };
        if ($mimeType === null || $mimeType !== $validated['mime_type'] || (int) ($asset['bytes'] ?? 0) > 15728640) {
            abort(422, 'The uploaded file is not an allowed image or exceeds the upload limit.');
        }

        $evidence = $survey->evidence()->firstOrCreate(['asset_id' => $asset['asset_id']], [
            'uploaded_by' => $request->user()->getKey(),
            'asset_id' => $asset['asset_id'],
            'public_id' => $asset['public_id'],
            'category_key' => $validated['category_key'],
            'category_label' => $categoryLabel,
            'source_document_identifier' => $survey->survey_type_slug_snapshot === 'offshore-helideck' ? 'HELIDECK REPORT FSO TOPAZ.docx' : 'Heliport inspection report NORTH BANK.docx',
            'source_table_number' => $survey->survey_type_slug_snapshot === 'offshore-helideck' ? 31 : 32,
            'original_filename' => basename($validated['original_filename']),
            'format' => $format,
            'mime_type' => $mimeType,
            'byte_size' => (int) $asset['bytes'],
            'width' => isset($asset['width']) ? (int) $asset['width'] : null,
            'height' => isset($asset['height']) ? (int) $asset['height'] : null,
            'caption' => $validated['caption'] ?? null,
            'cloudinary_version' => (int) $asset['version'],
            'uploaded_at' => now(),
        ]);

        return response()->json(['data' => $this->evidenceData($evidence, $cloudinary)], 201);
    }

    private function organizationForSurveys(string $publicId): Organization
    {
        $organization = Organization::query()->visibleTo(request()->user())->where('public_id', $publicId)->firstOrFail();
        Gate::authorize('viewSurveys', $organization);

        return $organization;
    }

    private function authorizedSurvey(Organization $organization, string $surveyPublicId, bool $forWrite): SurveyInstance
    {
        $query = $organization->surveys()->where('public_id', $surveyPublicId);
        $user = request()->user();
        if (! $this->canManage($organization, $user)) {
            $query->where('assigned_to_user_id', $user->getKey());
        }
        $survey = $query->with('evidence')->firstOrFail();
        if ($forWrite && ! $this->canManage($organization, $user) && (int) $survey->assigned_to_user_id !== (int) $user->getKey()) {
            abort(403);
        }

        return $survey;
    }

    private function canManage(Organization $organization, User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasOrganizationPermission($organization->getKey(), 'inspections.manage');
    }

}
