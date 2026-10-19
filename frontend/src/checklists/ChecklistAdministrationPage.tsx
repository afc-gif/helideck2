import { useEffect, useMemo, useState, type FormEvent } from 'react'
import { ApiError, api, type ChecklistDefinition, type ChecklistFieldType, type ChecklistLibrary, type ChecklistOption, type ChecklistQuestion, type ChecklistSection, type ChecklistVersion, type OrganizationSummary } from '../lib/api'
import './ChecklistAdministrationPage.css'

const fieldTypes: { value: ChecklistFieldType; label: string }[] = [
  { value: 'text', label: 'Short text' },
  { value: 'textarea', label: 'Long text' },
  { value: 'integer', label: 'Whole number' },
  { value: 'decimal', label: 'Decimal number' },
  { value: 'date', label: 'Date' },
  { value: 'time', label: 'Time' },
  { value: 'boolean', label: 'Yes / no' },
  { value: 'single_select', label: 'Single select' },
  { value: 'multi_select', label: 'Multiple select' },
  { value: 'informational', label: 'Information only' },
]

function emptyLibrary(): ChecklistLibrary {
  return { survey_types: [], checklists: [] }
}

function firstError(error: unknown): string {
  if (error instanceof ApiError) {
    const validationMessage = Object.values(error.errors).flat()[0]
    return validationMessage ?? error.message
  }
  return 'The request could not be completed. Check your connection and try again.'
}

function cloneSections(sections: ChecklistSection[]): ChecklistSection[] {
  return sections.map((section) => ({
    title: section.title,
    description: section.description ?? '',
    questions: section.questions.map((question) => ({
      ...question,
      description: question.description ?? '',
      instructions: question.instructions ?? '',
      unit: question.unit ?? '',
      options: question.options.map((option) => ({ ...option })),
    })),
  }))
}

export function ChecklistAdministrationPage() {
  const [organizations, setOrganizations] = useState<OrganizationSummary[]>([])
  const [organizationId, setOrganizationId] = useState('')
  const [library, setLibrary] = useState<ChecklistLibrary>(emptyLibrary)
  const [selectedChecklistId, setSelectedChecklistId] = useState('')
  const [selectedVersionNumber, setSelectedVersionNumber] = useState<number | null>(null)
  const [search, setSearch] = useState('')
  const [loadingOrganizations, setLoadingOrganizations] = useState(true)
  const [loadedOrganizationId, setLoadedOrganizationId] = useState('')
  const [pageError, setPageError] = useState<string | null>(null)
  const [creating, setCreating] = useState(false)

  useEffect(() => {
    let active = true
    api.organizations()
      .then((items) => {
        if (!active) return
        setOrganizations(items)
        setOrganizationId(items[0]?.public_id ?? '')
      })
      .catch((error: unknown) => { if (active) setPageError(firstError(error)) })
      .finally(() => { if (active) setLoadingOrganizations(false) })
    return () => { active = false }
  }, [])

  useEffect(() => {
    if (!organizationId) {
      return
    }
    let active = true
    api.checklists(organizationId)
      .then((result) => {
        if (!active) return
        setLibrary(result)
        setLoadedOrganizationId(organizationId)
        setSelectedChecklistId((current) => result.checklists.some((checklist) => checklist.public_id === current) ? current : '')
      })
      .catch((error: unknown) => {
        if (active) {
          setPageError(firstError(error))
          setLoadedOrganizationId(organizationId)
        }
      })
    return () => { active = false }
  }, [organizationId])

  const selectedChecklist = library.checklists.find((checklist) => checklist.public_id === selectedChecklistId) ?? null
  const selectedOrganization = organizations.find((organization) => organization.public_id === organizationId)
  const loadingLibrary = Boolean(organizationId && loadedOrganizationId !== organizationId)
  const filteredChecklists = useMemo(() => library.checklists.filter((checklist) =>
    `${checklist.name} ${checklist.survey_type.name}`.toLowerCase().includes(search.toLowerCase().trim())), [library.checklists, search])

  const activeVersionNumber = selectedChecklist?.versions.some((version) => version.version_number === selectedVersionNumber)
    ? selectedVersionNumber
    : selectedChecklist?.versions.at(-1)?.version_number ?? null
  const selectedVersion = selectedChecklist?.versions.find((version) => version.version_number === activeVersionNumber) ?? null

  function replaceChecklist(updated: ChecklistDefinition) {
    setLibrary((current) => ({
      ...current,
      checklists: current.checklists.some((checklist) => checklist.public_id === updated.public_id)
        ? current.checklists.map((checklist) => checklist.public_id === updated.public_id ? updated : checklist)
        : [...current.checklists, updated].sort((first, second) => first.name.localeCompare(second.name)),
    }))
  }

  async function createChecklist(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (!organizationId) return
    const formElement = event.currentTarget
    const form = new FormData(formElement)
    setPageError(null)
    setCreating(true)
    try {
      const definition = await api.createChecklist(organizationId, {
        name: String(form.get('name') ?? '').trim(),
        description: String(form.get('description') ?? '').trim(),
        survey_type: String(form.get('survey_type') ?? ''),
      })
      replaceChecklist(definition)
      setSelectedChecklistId(definition.public_id)
      setSelectedVersionNumber(1)
      formElement.reset()
    } catch (error) {
      setPageError(firstError(error))
    } finally {
      setCreating(false)
    }
  }

  async function createDraftVersion() {
    if (!organizationId || !selectedChecklist) return
    setPageError(null)
    try {
      const updated = await api.createChecklistVersion(organizationId, selectedChecklist.public_id)
      replaceChecklist(updated)
      setSelectedVersionNumber(updated.versions.at(-1)?.version_number ?? null)
    } catch (error) {
      setPageError(firstError(error))
    }
  }

  return (
    <section className="checklist-page">
      <div className="page-heading checklist-page-heading">
        <div><div className="eyebrow">SURVEY SETUP</div><h1>Checklist administration</h1><p>Build and publish versioned survey templates for your organization.</p></div>
        {organizations.length > 1 && <label className="organization-picker">Organization
          <select value={organizationId} onChange={(event) => { setOrganizationId(event.target.value); setSelectedChecklistId(''); setPageError(null) }}>
            {organizations.map((organization) => <option key={organization.public_id} value={organization.public_id}>{organization.name}</option>)}
          </select>
        </label>}
      </div>

      {pageError && <div className="checklist-alert" role="alert">{pageError}</div>}
      <div className="source-note"><strong>Approved survey content</strong><span>No question bank is preloaded. Author technical requirements only from ARTSCI’s approved source documents.</span></div>

      {loadingOrganizations ? <div className="checklist-state" role="status">Loading organizations…</div>
        : organizations.length === 0 ? <div className="checklist-state"><strong>No organization workspace is available.</strong><span>Ask your platform administrator to add you to an organization.</span></div>
          : <div className="checklist-workspace">
            <aside className="checklist-library panel">
              <div className="library-heading"><div><h2>Templates</h2><span>{selectedOrganization?.name ?? 'Organization'}</span></div><span className="library-total">{library.checklists.length}</span></div>
              <label className="visually-hidden" htmlFor="checklist-search">Search templates</label>
              <input id="checklist-search" className="checklist-search" value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Search templates" />
              <form className="create-template-form" onSubmit={(event) => void createChecklist(event)}>
                <h3>New template</h3>
                <label>Template name<input name="name" required maxLength={255} placeholder="e.g. Survey template" /></label>
                <label>Survey setting<select name="survey_type" required defaultValue={library.survey_types[0]?.slug ?? ''}>
                  {library.survey_types.map((type) => <option key={type.slug} value={type.slug}>{type.name}</option>)}
                </select></label>
                <label>Description <span className="optional-label">Optional</span><textarea name="description" rows={2} maxLength={5000} placeholder="Describe when this template is used" /></label>
                <button className="primary-button create-template-button" type="submit" disabled={creating || loadingLibrary || library.survey_types.length === 0}>{creating ? 'Creating…' : 'Create draft template'}</button>
              </form>
              <div className="template-list-heading">TEMPLATE LIBRARY</div>
              {loadingLibrary ? <div className="library-state">Loading templates…</div>
                : filteredChecklists.length === 0 ? <div className="library-state">{library.checklists.length ? 'No templates match this search.' : 'No templates yet. Create a draft to begin.'}</div>
                  : <div className="template-list">{filteredChecklists.map((checklist) => {
                    const latest = checklist.versions.at(-1)
                    return <button key={checklist.public_id} type="button" className={`template-list-item ${selectedChecklistId === checklist.public_id ? 'template-list-item-active' : ''}`} onClick={() => setSelectedChecklistId(checklist.public_id)}>
                      <span className="template-list-name">{checklist.name}</span><span className="template-list-meta">{checklist.survey_type.name} · v{latest?.version_number ?? 1}</span><span className={`version-badge ${latest?.status === 'published' ? 'published-badge' : ''}`}>{latest?.status ?? 'draft'}</span>
                    </button>
                  })}</div>}
            </aside>

            <div className="checklist-editor-area">
              {selectedChecklist && selectedVersion
                ? <ChecklistEditor key={`${selectedChecklist.public_id}-${selectedVersion.version_number}`} organizationId={organizationId} checklist={selectedChecklist} version={selectedVersion} onChange={replaceChecklist} onSelectVersion={setSelectedVersionNumber} onCreateVersion={() => void createDraftVersion()} />
                : <div className="checklist-empty-editor panel"><span className="empty-icon">☷</span><strong>Select a template to edit</strong><span>Published versions remain available while you prepare the next draft.</span></div>}
            </div>
          </div>}
    </section>
  )
}

function ChecklistEditor({ organizationId, checklist, version, onChange, onSelectVersion, onCreateVersion }: {
  organizationId: string
  checklist: ChecklistDefinition
  version: ChecklistVersion
  onChange: (definition: ChecklistDefinition) => void
  onSelectVersion: (versionNumber: number) => void
  onCreateVersion: () => void
}) {
  const [name, setName] = useState(checklist.name)
  const [description, setDescription] = useState(checklist.description ?? '')
  const [sections, setSections] = useState(() => cloneSections(version.sections))
  const [saving, setSaving] = useState(false)
  const [publishing, setPublishing] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [notice, setNotice] = useState<string | null>(null)
  const isDraft = version.status === 'draft'
  const hasDraft = checklist.versions.some((item) => item.status === 'draft')

  async function save(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setSaving(true)
    setError(null)
    setNotice(null)
    try {
      let updated = checklist
      if (name.trim() !== checklist.name || description.trim() !== (checklist.description ?? '')) {
        updated = await api.updateChecklist(organizationId, checklist.public_id, { name: name.trim(), description: description.trim() })
      }
      if (isDraft) {
        updated = await api.saveChecklistVersion(organizationId, checklist.public_id, version.version_number, sections)
      }
      onChange(updated)
      setNotice(isDraft ? 'Draft saved.' : 'Template details saved.')
    } catch (cause) {
      setError(firstError(cause))
    } finally {
      setSaving(false)
    }
  }

  async function publish() {
    setPublishing(true)
    setError(null)
    setNotice(null)
    try {
      const updated = await api.publishChecklistVersion(organizationId, checklist.public_id, version.version_number)
      onChange(updated)
      setNotice(`Version ${version.version_number} published.`)
    } catch (cause) {
      setError(firstError(cause))
    } finally {
      setPublishing(false)
    }
  }

  function updateSection(sectionIndex: number, updatedSection: ChecklistSection) {
    setSections((current) => current.map((section, index) => index === sectionIndex ? updatedSection : section))
  }

  return <form className="checklist-editor panel" onSubmit={(event) => void save(event)}>
    <div className="editor-header">
      <div><div className="eyebrow">{checklist.survey_type.name.toUpperCase()}</div><h2>Template details</h2></div>
      <div className="editor-actions">
        <span className={`version-badge editor-status ${version.status === 'published' ? 'published-badge' : ''}`}>Version {version.version_number} · {version.status}</span>
        {!hasDraft && <button className="secondary-button" type="button" onClick={onCreateVersion}>Create new draft version</button>}
        <button className="primary-button" type="submit" disabled={saving}>{saving ? 'Saving…' : isDraft ? 'Save draft' : 'Save details'}</button>
      </div>
    </div>

    <div className="version-history" aria-label="Template versions">{checklist.versions.map((item) => <button type="button" key={item.version_number} className={`version-history-item ${item.version_number === version.version_number ? 'version-history-current' : ''}`} aria-current={item.version_number === version.version_number ? 'page' : undefined} onClick={() => onSelectVersion(item.version_number)}>
      v{item.version_number} <small>{item.status}</small>
    </button>)}</div>

    <div className="editor-fields">
      <label>Template name<input value={name} onChange={(event) => setName(event.target.value)} required maxLength={255} /></label>
      <label>Description<textarea value={description} onChange={(event) => setDescription(event.target.value)} rows={2} maxLength={5000} /></label>
    </div>

    <div className="version-content-heading"><div><h3>Checklist content</h3><p>{isDraft ? 'Arrange sections and author questions for this draft.' : `Published ${version.published_at ? new Date(version.published_at).toLocaleString() : ''}. This version cannot be edited.`}</p></div>
      {isDraft && <button className="secondary-button" type="button" onClick={() => setSections((current) => [...current, { title: '', description: '', questions: [] }])}>＋ Add section</button>}
    </div>

    {sections.length === 0 ? <div className="content-empty">{isDraft ? 'Add a section to start building this checklist.' : 'This version contains no sections.'}</div>
      : sections.map((section, sectionIndex) => <section className="checklist-section-editor" key={`${sectionIndex}-${section.title}`}>
        <div className="section-editor-top"><span className="section-number">{String(sectionIndex + 1).padStart(2, '0')}</span>
          {isDraft ? <>
            <input className="section-title-input" value={section.title} onChange={(event) => updateSection(sectionIndex, { ...section, title: event.target.value })} placeholder="Section title" required maxLength={255} aria-label={`Section ${sectionIndex + 1} title`} />
            <button className="icon-text-button" type="button" disabled={sectionIndex === 0} onClick={() => setSections((current) => moveItem(current, sectionIndex, -1))} aria-label="Move section up">↑</button>
            <button className="icon-text-button" type="button" disabled={sectionIndex === sections.length - 1} onClick={() => setSections((current) => moveItem(current, sectionIndex, 1))} aria-label="Move section down">↓</button>
            <button className="icon-text-button danger-text" type="button" onClick={() => setSections((current) => current.filter((_, index) => index !== sectionIndex))}>Remove</button>
          </> : <strong className="section-title-readonly">{section.title}</strong>}
        </div>
        {isDraft && <label className="section-description-label">Section description<textarea value={section.description ?? ''} rows={2} maxLength={5000} onChange={(event) => updateSection(sectionIndex, { ...section, description: event.target.value })} /></label>}
        <div className="question-editor-list">{section.questions.map((question, questionIndex) => <QuestionEditor key={question.stable_key} question={question} index={questionIndex} count={section.questions.length} editable={isDraft}
          onChange={(updatedQuestion) => updateSection(sectionIndex, { ...section, questions: section.questions.map((current, index) => index === questionIndex ? updatedQuestion : current) })}
          onMove={(direction) => updateSection(sectionIndex, { ...section, questions: moveItem(section.questions, questionIndex, direction) })}
          onRemove={() => updateSection(sectionIndex, { ...section, questions: section.questions.filter((_, index) => index !== questionIndex) })} />)}</div>
        {isDraft && <button className="add-question-button" type="button" onClick={() => updateSection(sectionIndex, { ...section, questions: [...section.questions, newQuestion()] })}>＋ Add question</button>}
      </section>)}

    {error && <div className="checklist-alert" role="alert">{error}</div>}
    {notice && <div className="checklist-notice" role="status">{notice}</div>}
    {isDraft && <div className="publish-row"><p>Publishing locks this version. To make later changes, create a new draft version.</p><button type="button" className="publish-button" onClick={() => void publish()} disabled={publishing || saving}>{publishing ? 'Publishing…' : 'Publish version'}</button></div>}
  </form>
}

function QuestionEditor({ question, index, count, editable, onChange, onMove, onRemove }: {
  question: ChecklistQuestion
  index: number
  count: number
  editable: boolean
  onChange: (question: ChecklistQuestion) => void
  onMove: (direction: number) => void
  onRemove: () => void
}) {
  const optionsEnabled = question.field_type === 'single_select' || question.field_type === 'multi_select'
  return <article className="question-editor">
    <div className="question-editor-header"><span className="question-grip">Q{index + 1}</span>
      {editable ? <>
        <input className="question-label-input" value={question.label} onChange={(event) => onChange({ ...question, label: event.target.value })} placeholder="Question or information label" required maxLength={500} aria-label={`Question ${index + 1} label`} />
        <button className="icon-text-button" type="button" disabled={index === 0} onClick={() => onMove(-1)} aria-label="Move question up">↑</button>
        <button className="icon-text-button" type="button" disabled={index === count - 1} onClick={() => onMove(1)} aria-label="Move question down">↓</button>
        <button className="icon-text-button danger-text" type="button" onClick={onRemove}>Remove</button>
      </> : <strong>{question.label}</strong>}
    </div>
    {editable ? <div className="question-fields">
      <label>Response type<select value={question.field_type} onChange={(event) => {
        const fieldType = event.target.value as ChecklistFieldType
        onChange({ ...question, field_type: fieldType, options: fieldType === 'single_select' || fieldType === 'multi_select' ? question.options : [] })
      }}>{fieldTypes.map((fieldType) => <option key={fieldType.value} value={fieldType.value}>{fieldType.label}</option>)}</select></label>
      <label>Question description <span className="optional-label">Optional</span><textarea value={question.description ?? ''} onChange={(event) => onChange({ ...question, description: event.target.value })} rows={2} maxLength={5000} /></label>
      <label>Instructions / source notes <span className="optional-label">Optional</span><textarea value={question.instructions ?? ''} onChange={(event) => onChange({ ...question, instructions: event.target.value })} rows={2} maxLength={5000} /><small>Preserve unresolved source wording and record ambiguities here; do not normalize conflicting values.</small></label>
      <label className="required-toggle"><input type="checkbox" checked={question.is_required} onChange={(event) => onChange({ ...question, is_required: event.target.checked })} /> Required response</label>
    </div> : <div className="question-readonly-meta"><span>{fieldTypes.find((item) => item.value === question.field_type)?.label ?? question.field_type}</span>{question.is_required && <span>Required</span>}</div>}

    {optionsEnabled && <div className="response-options"><div className="response-options-heading"><strong>Response options</strong>{editable && <button className="text-link" type="button" onClick={() => onChange({ ...question, options: [...question.options, { value: '', label: '' }] })}>＋ Add option</button>}</div>
      {question.options.length === 0 ? <span className="option-empty">Add response options for this question.</span> : question.options.map((option, optionIndex) => <OptionEditor key={`${index}-${optionIndex}`} option={option} index={optionIndex} editable={editable}
        onChange={(updatedOption) => onChange({ ...question, options: question.options.map((current, currentIndex) => currentIndex === optionIndex ? updatedOption : current) })}
        onRemove={() => onChange({ ...question, options: question.options.filter((_, currentIndex) => currentIndex !== optionIndex) })} />)}
    </div>}
  </article>
}

function OptionEditor({ option, index, editable, onChange, onRemove }: {
  option: ChecklistOption
  index: number
  editable: boolean
  onChange: (option: ChecklistOption) => void
  onRemove: () => void
}) {
  if (!editable) return <div className="option-readonly">{option.label} <code>{option.value}</code></div>
  return <div className="option-editor-row"><label>Value<input value={option.value} onChange={(event) => onChange({ ...option, value: event.target.value })} required maxLength={255} aria-label={`Option ${index + 1} value`} /></label><label>Label<input value={option.label} onChange={(event) => onChange({ ...option, label: event.target.value })} required maxLength={255} aria-label={`Option ${index + 1} label`} /></label><button className="icon-text-button danger-text" type="button" onClick={onRemove}>Remove</button></div>
}

function newQuestion(): ChecklistQuestion {
  return {
    stable_key: crypto.randomUUID(),
    label: '',
    description: '',
    instructions: '',
    field_type: 'text',
    is_required: false,
    unit: '',
    options: [],
  }
}

function moveItem<T>(items: T[], index: number, direction: number): T[] {
  const nextIndex = index + direction
  if (nextIndex < 0 || nextIndex >= items.length) return items
  const updated = [...items]
  ;[updated[index], updated[nextIndex]] = [updated[nextIndex], updated[index]]
  return updated
}
