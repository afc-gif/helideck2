import { useEffect, useMemo, useState, type FormEvent } from 'react'
import { ApiError, api, type OrganizationSummary, type SurveyAnswer, type SurveyInstance, type SurveySummary, type SurveyTemplate } from '../lib/api'
import './SurveyTakingPage.css'

function errorMessage(error: unknown): string {
  if (error instanceof ApiError) {
    return Object.values(error.errors).flat()[0] ?? error.message
  }
  return 'The request could not be completed. Check your connection and try again.'
}

function emptyValue(questionType: string): unknown {
  return questionType === 'multi_select' ? [] : null
}

export function SurveyTakingPage() {
  const [organizations, setOrganizations] = useState<OrganizationSummary[]>([])
  const [organizationId, setOrganizationId] = useState('')
  const [templates, setTemplates] = useState<SurveyTemplate[]>([])
  const [surveys, setSurveys] = useState<SurveySummary[]>([])
  const [activeSurvey, setActiveSurvey] = useState<SurveyInstance | null>(null)
  const [templateId, setTemplateId] = useState('')
  const [versionNumber, setVersionNumber] = useState<number | null>(null)
  const [siteName, setSiteName] = useState('')
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [notice, setNotice] = useState<string | null>(null)

  useEffect(() => {
    let current = true
    api.organizations().then((items) => {
      if (current) {
        setOrganizations(items)
        setOrganizationId(items[0]?.public_id ?? '')
      }
    }).catch((cause: unknown) => { if (current) setError(errorMessage(cause)) }).finally(() => { if (current) setLoading(false) })
    return () => { current = false }
  }, [])

  useEffect(() => {
    if (!organizationId) return
    let current = true
    Promise.all([api.surveyTemplates(organizationId), api.surveys(organizationId)]).then(([availableTemplates, existingSurveys]) => {
      if (current) {
        setTemplates(availableTemplates)
        setSurveys(existingSurveys)
        setTemplateId((selected) => availableTemplates.some((template) => template.public_id === selected) ? selected : availableTemplates[0]?.public_id ?? '')
      }
    }).catch((cause: unknown) => { if (current) setError(errorMessage(cause)) })
    return () => { current = false }
  }, [organizationId])

  const selectedTemplate = templates.find((template) => template.public_id === templateId) ?? null
  const selectedVersions = selectedTemplate?.versions ?? []
  const selectedVersionNumber = selectedVersions.some((version) => version.version_number === versionNumber)
    ? versionNumber
    : selectedVersions.at(-1)?.version_number ?? null
  const activeAnswers = useMemo(() => activeSurvey?.answers ?? {}, [activeSurvey])

  async function refreshSurveys() {
    if (!organizationId) return
    setSurveys(await api.surveys(organizationId))
  }

  async function createSurvey(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (!organizationId || !selectedTemplate || selectedVersionNumber === null) return
    setSaving(true)
    setError(null)
    try {
      const created = await api.createSurvey(organizationId, {
        checklist_public_id: selectedTemplate.public_id,
        version_number: selectedVersionNumber,
        site_name: siteName.trim(),
      })
      setActiveSurvey(created)
      setSiteName('')
      await refreshSurveys()
    } catch (cause) {
      setError(errorMessage(cause))
    } finally {
      setSaving(false)
    }
  }

  async function openSurvey(survey: SurveySummary) {
    if (!organizationId) return
    setError(null)
    setNotice(null)
    try {
      setActiveSurvey(await api.survey(organizationId, survey.public_id))
    } catch (cause) {
      setError(errorMessage(cause))
    }
  }

  async function saveSurvey(status: 'in_progress' | 'completed') {
    if (!organizationId || !activeSurvey) return
    setSaving(true)
    setError(null)
    setNotice(null)
    try {
      const answers = activeSurvey.sections.flatMap((section) => section.questions.map((question) => {
        const answer = activeAnswers[question.public_id]
        return { question_public_id: question.public_id, value: answer?.value ?? emptyValue(question.field_type), comment: answer?.comment ?? null }
      }))
      const updated = await api.saveSurveyAnswers(organizationId, activeSurvey.public_id, answers, status)
      setActiveSurvey(updated)
      setNotice(status === 'completed' ? 'Survey completed and saved.' : 'Progress saved. You can resume this survey later.')
      await refreshSurveys()
    } catch (cause) {
      setError(errorMessage(cause))
    } finally {
      setSaving(false)
    }
  }

  function changeAnswer(questionId: string, patch: Partial<SurveyAnswer>) {
    setActiveSurvey((current) => {
      if (!current) return current
      const existing = current.answers[questionId] ?? { value: null, comment: null }
      return { ...current, answers: { ...current.answers, [questionId]: { ...existing, ...patch } } }
    })
  }

  if (loading) return <section className="survey-page"><div className="survey-panel">Loading survey workspace…</div></section>

  return <section className="survey-page">
    <div className="page-heading survey-heading">
      <div><div className="eyebrow">FIELD OPERATIONS</div><h1>Inspections</h1><p>Start a survey from a published checklist version or resume an in-progress survey.</p></div>
      {organizations.length > 1 && <label className="survey-org-picker">Organization<select value={organizationId} onChange={(event) => { setActiveSurvey(null); setError(null); setOrganizationId(event.target.value) }}>{organizations.map((organization) => <option key={organization.public_id} value={organization.public_id}>{organization.name}</option>)}</select></label>}
    </div>
    {error && <div className="survey-alert" role="alert">{error}</div>}
    {notice && <div className="survey-notice" role="status">{notice}</div>}
    <div className="survey-layout">
      <aside className="survey-panel survey-list-panel">
        <div className="survey-panel-heading"><div><h2>Surveys</h2><p>{surveys.length} in this workspace</p></div></div>
        {surveys.length === 0 ? <div className="survey-empty-list">No surveys yet.</div> : <div className="survey-list">{surveys.map((survey) => <button key={survey.public_id} className={`survey-list-item ${activeSurvey?.public_id === survey.public_id ? 'survey-list-item-active' : ''}`} type="button" onClick={() => void openSurvey(survey)}>
          <strong>{survey.site_name}</strong><span>{survey.checklist.name} · v{survey.checklist.version_number}</span><small>{survey.checklist.survey_type === 'offshore-helideck' ? 'Offshore helideck' : 'Onshore heliport'} · {survey.status.replace('_', ' ')}</small>
        </button>)}</div>}
        <form className="survey-create-form" onSubmit={(event) => void createSurvey(event)}>
          <h3>Start a survey</h3>
          <label>Published checklist<select required value={templateId} onChange={(event) => { setTemplateId(event.target.value); setVersionNumber(null) }} disabled={templates.length === 0}>
            {templates.length === 0 && <option value="">No published templates</option>}
            {templates.map((template) => <option key={template.public_id} value={template.public_id}>{template.name} · {template.survey_type.name}</option>)}
          </select></label>
          <label>Checklist version<select required value={selectedVersionNumber ?? ''} onChange={(event) => setVersionNumber(Number(event.target.value))} disabled={selectedVersions.length === 0}>
            {selectedVersions.map((version) => <option key={version.version_number} value={version.version_number}>Version {version.version_number}</option>)}
          </select></label>
          <label>Installation / landing site<input required maxLength={255} value={siteName} onChange={(event) => setSiteName(event.target.value)} placeholder="Enter site name" /></label>
          <button className="primary-button" type="submit" disabled={saving || !templateId || selectedVersionNumber === null}>{saving ? 'Starting…' : 'Start survey'}</button>
        </form>
      </aside>
      <main className="survey-panel survey-editor-panel">
        {!activeSurvey ? <div className="survey-editor-empty"><span className="empty-icon">▤</span><strong>Choose a survey to resume</strong><span>Starting a survey pins the selected published checklist version.</span></div> : <>
          <div className="survey-editor-header"><div><div className="eyebrow">{activeSurvey.checklist.survey_type === 'offshore-helideck' ? 'OFFSHORE HELIDECK' : 'ONSHORE HELIPORT'} · VERSION {activeSurvey.checklist.version_number}</div><h2>{activeSurvey.site_name}</h2><p>{activeSurvey.checklist.name} · Started {new Date(activeSurvey.started_at).toLocaleDateString()}</p></div><span className={`survey-status ${activeSurvey.status}`}>{activeSurvey.status.replace('_', ' ')}</span></div>
          {activeSurvey.sections.map((section) => <section className="survey-question-section" key={section.public_id}><div className="survey-section-heading"><h3>{section.title}</h3>{section.description && <p>{section.description}</p>}</div>
            {section.questions.map((question) => {
              const answer = activeAnswers[question.public_id] ?? { value: emptyValue(question.field_type), comment: null }
              const controlId = `answer-${question.public_id}`
              return <div className="survey-question" key={question.public_id}>
                <label htmlFor={controlId}>{question.label}{question.is_required && <span className="required-mark"> *</span>}</label>
                {question.description && <p className="survey-question-description">{question.description}</p>}
                {question.instructions && <p className="survey-question-instructions">{question.instructions}</p>}
                {question.field_type === 'informational' ? <div className="survey-information">Information only</div>
                  : question.field_type === 'boolean' ? <select id={controlId} value={answer.value === true ? 'true' : answer.value === false ? 'false' : ''} onChange={(event) => changeAnswer(question.public_id, { value: event.target.value === '' ? null : event.target.value === 'true' })}><option value="">Select…</option><option value="true">Yes</option><option value="false">No</option></select>
                    : question.field_type === 'single_select' ? <select id={controlId} value={typeof answer.value === 'string' ? answer.value : ''} onChange={(event) => changeAnswer(question.public_id, { value: event.target.value || null })}><option value="">Select…</option>{question.options.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}</select>
                      : question.field_type === 'multi_select' ? <div className="survey-multi-select">{question.options.map((option) => <label key={option.value}><input type="checkbox" checked={Array.isArray(answer.value) && answer.value.includes(option.value)} onChange={(event) => {
                        const selected = Array.isArray(answer.value) ? answer.value.filter((value): value is string => typeof value === 'string') : []
                        changeAnswer(question.public_id, { value: event.target.checked ? [...selected, option.value] : selected.filter((value) => value !== option.value) })
                      }} />{option.label}</label>)}</div>
                        : question.field_type === 'textarea' ? <textarea id={controlId} rows={3} value={typeof answer.value === 'string' ? answer.value : ''} onChange={(event) => changeAnswer(question.public_id, { value: event.target.value || null })} />
                          : <input id={controlId} type={question.field_type === 'integer' || question.field_type === 'decimal' ? 'number' : question.field_type === 'date' ? 'date' : question.field_type === 'time' ? 'time' : 'text'} step={question.field_type === 'decimal' ? 'any' : question.field_type === 'integer' ? '1' : undefined} value={answer.value === null || answer.value === undefined ? '' : String(answer.value)} onChange={(event) => {
                            const raw = event.target.value
                            const value = raw === '' ? null : question.field_type === 'integer' || question.field_type === 'decimal' ? Number(raw) : raw
                            changeAnswer(question.public_id, { value })
                          }} />}
                {question.unit && <small className="survey-unit">Unit: {question.unit}</small>}
                {question.field_type !== 'informational' && <label className="survey-comment-label">Comment <span>(optional)</span><textarea rows={2} value={answer.comment ?? ''} onChange={(event) => changeAnswer(question.public_id, { comment: event.target.value || null })} placeholder="Add an observation or context" /></label>}
              </div>
            })}
          </section>)}
          <div className="survey-actions"><span>Answers are saved to this survey’s pinned version.</span><div><button type="button" className="secondary-button" disabled={saving || activeSurvey.status === 'completed'} onClick={() => void saveSurvey('in_progress')}>Save progress</button><button type="button" className="primary-button" disabled={saving || activeSurvey.status === 'completed'} onClick={() => void saveSurvey('completed')}>{saving ? 'Saving…' : 'Complete survey'}</button></div></div>
        </>}
      </main>
    </div>
  </section>
}
