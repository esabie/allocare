function humanizeFieldName(name) {
    if (!name) return 'Field';
    return name
        .replace(/_/g, ' ')
        .replace(/\b\w/g, (char) => char.toUpperCase());
}

function getFieldLabel(element, root) {
    if (element.id) {
        const explicit = root.querySelector(`label[for="${element.id}"]`);
        if (explicit?.textContent?.trim()) {
            return explicit.textContent.trim();
        }
    }

    const parentLabel = element.closest('label');
    if (parentLabel) {
        const clone = parentLabel.cloneNode(true);
        clone.querySelectorAll('input, textarea, select').forEach((node) => node.remove());
        const text = clone.textContent?.trim();
        if (text) return text;
    }

    const siblingLabel = element.parentElement?.querySelector('label');
    if (siblingLabel?.textContent?.trim()) {
        return siblingLabel.textContent.trim();
    }

    const prev = element.parentElement?.previousElementSibling;
    if (prev?.tagName === 'LABEL' && prev.textContent?.trim()) {
        return prev.textContent.trim();
    }

    return humanizeFieldName(element.name);
}

function getFieldValue(element) {
    if (element.type === 'checkbox') {
        return element.checked ? 'Yes' : 'No';
    }
    if (element.type === 'radio') {
        return element.checked ? element.value || 'Selected' : null;
    }
    const value = String(element.value ?? '').trim();
    if (value) return value;
    if (element.placeholder) return element.placeholder;
    return '—';
}

function findSectionTitle(element, headings) {
    let title = 'Care plan details';
    for (const heading of headings) {
        if (heading.compareDocumentPosition(element) & Node.DOCUMENT_POSITION_FOLLOWING) {
            title = heading.textContent?.trim() || title;
        }
    }
    return title;
}

function isSkippableElement(element, root) {
    if (!element) return true;
    if (['hidden', 'button', 'submit', 'reset', 'file'].includes(element.type)) return true;
    if (element.closest('[data-print-exclude]')) return true;
    if (element.closest('header, aside, nav')) return true;
    if (element.closest('button')) return true;
    if (!root.contains(element)) return true;
    return false;
}

export function collectCarePlanPrintSections(root) {
    if (!root) return [];

    const headings = Array.from(
        root.querySelectorAll('h2.text-xl, h2.text-base.font-semibold, h2.mb-4'),
    ).filter((heading) => !heading.closest('[data-print-exclude]'));

    const sections = new Map();
    const radioGroups = new Map();

    const ensureSection = (title) => {
        if (!sections.has(title)) {
            sections.set(title, []);
        }
        return sections.get(title);
    };

    root.querySelectorAll('input, textarea, select').forEach((element) => {
        if (isSkippableElement(element, root)) return;

        if (element.type === 'radio') {
            const key = element.name || getFieldLabel(element, root);
            if (!radioGroups.has(key)) {
                radioGroups.set(key, { element, seen: false });
            }
            if (element.checked) {
                radioGroups.set(key, { element, seen: true });
            }
            return;
        }

        const label = getFieldLabel(element, root);
        const value = getFieldValue(element);
        const sectionTitle = findSectionTitle(element, headings);
        const fields = ensureSection(sectionTitle);

        const duplicate = fields.some((field) => field.label === label);
        if (!duplicate) {
            fields.push({ label, value });
        }
    });

    radioGroups.forEach(({ element, seen }) => {
        if (!seen || !element) return;
        const label = getFieldLabel(element, root);
        const value = getFieldValue(element);
        const sectionTitle = findSectionTitle(element, headings);
        ensureSection(sectionTitle).push({ label, value });
    });

    return Array.from(sections.entries())
        .map(([title, fields]) => ({ title, fields }))
        .filter((section) => section.fields.length > 0);
}

function escapeHtml(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function buildPrintHtml({ planName, patientProfile, sections, printedAt, signOffRoles = [] }) {
    const allergies = (patientProfile.allergies || []).join(', ') || 'None recorded';
    const sectionHtml = sections
        .map(
            (section) => `
            <section class="print-section">
                <h2>${escapeHtml(section.title)}</h2>
                <div class="fields">
                    ${section.fields
                        .map((field) => {
                            const wide =
                                field.value.length > 72 || field.value.includes('\n') ? ' full-width' : '';
                            return `
                        <div class="field${wide}">
                            <div class="label">${escapeHtml(field.label)}</div>
                            <div class="value">${escapeHtml(field.value)}</div>
                        </div>`;
                        })
                        .join('')}
                </div>
            </section>`,
        )
        .join('');

    const signatureHtml = signOffRoles
        .map(
            (role) => `
            <div class="signature-block">
                <div class="signature-line"></div>
                <div class="signature-role">${escapeHtml(role)}</div>
            </div>`,
        )
        .join('');

    return `<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>${escapeHtml(planName)} — Care Plan</title>
    <style>
        @page { size: A4; margin: 14mm 12mm; }
        * { box-sizing: border-box; }
        body {
            font-family: "Segoe UI", system-ui, -apple-system, sans-serif;
            font-size: 11px;
            color: #0f172a;
            line-height: 1.45;
            margin: 0;
            padding: 0;
        }
        .brand {
            text-align: center;
            font-size: 22px;
            font-weight: 700;
            letter-spacing: -0.02em;
            color: #0f766e;
            margin-bottom: 4px;
        }
        h1 {
            margin: 0 0 6px;
            font-size: 20px;
            text-align: center;
            font-weight: 700;
        }
        .meta {
            text-align: center;
            color: #475569;
            font-size: 10px;
            margin-bottom: 16px;
        }
        .patient-banner {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 18px;
            background: #f8fafc;
        }
        .patient-banner .item .label {
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            color: #64748b;
            margin-bottom: 2px;
        }
        .patient-banner .item .value {
            font-size: 12px;
            font-weight: 600;
            color: #0f172a;
        }
        .status-pill {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 999px;
            background: #d1fae5;
            color: #047857;
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .print-section {
            margin-bottom: 16px;
            break-inside: avoid-page;
        }
        .print-section h2 {
            margin: 0 0 8px;
            font-size: 13px;
            border-bottom: 1px solid #cbd5e1;
            padding-bottom: 4px;
        }
        .fields {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px 16px;
        }
        .field { break-inside: avoid; }
        .field.full-width { grid-column: 1 / -1; }
        .label {
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            color: #64748b;
            margin-bottom: 2px;
        }
        .value {
            white-space: pre-wrap;
            font-size: 11px;
            color: #0f172a;
            min-height: 1.2em;
            border-left: 2px solid #e2e8f0;
            padding-left: 8px;
        }
        .signatures {
            margin-top: 20px;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
        }
        .signature-block { text-align: center; }
        .signature-line {
            border-bottom: 1px solid #334155;
            height: 36px;
            margin-bottom: 6px;
        }
        .signature-role {
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            color: #64748b;
        }
        .footer {
            margin-top: 22px;
            padding-top: 8px;
            border-top: 1px solid #e2e8f0;
            font-size: 9px;
            color: #64748b;
            text-align: right;
        }
        @media print {
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>
    <div class="brand">AlloCare</div>
    <h1>${escapeHtml(planName)}</h1>
    <div class="meta">Care plan record — printed ${escapeHtml(printedAt)}</div>

    <div class="patient-banner">
        <div class="item">
            <div class="label">Full name</div>
            <div class="value">${escapeHtml(patientProfile.fullName)}</div>
        </div>
        <div class="item">
            <div class="label">Patient reference</div>
            <div class="value">${escapeHtml(patientProfile.reference)}</div>
        </div>
        <div class="item">
            <div class="label">Date of birth</div>
            <div class="value">${escapeHtml(patientProfile.dob)}</div>
        </div>
        <div class="item">
            <div class="label">Allergies</div>
            <div class="value">${escapeHtml(allergies)}</div>
        </div>
    </div>

    <div style="margin-bottom: 14px;">
        <span class="status-pill">Active care plan</span>
    </div>

    ${sectionHtml || '<p class="meta">No form fields captured for this plan.</p>'}

    <div class="signatures">
        ${signatureHtml}
    </div>

    <div class="footer">
        Confidential — for care delivery and governance only. Generated from AlloCare.
    </div>
</body>
</html>`;
}

function collectSignatureRoles(container) {
    const roles = Array.from(container?.querySelectorAll('button') ?? [])
        .map((button) => button.textContent?.match(/Click to sign digitally \((.+)\)/)?.[1]?.trim())
        .filter(Boolean);
    return roles.length > 0 ? roles : ['Person / Patient', 'Manager', 'Clinical Lead'];
}

export function printCarePlan({
    planName,
    patientProfile,
    container,
    signOffRoles,
}) {
    const sections = collectCarePlanPrintSections(container);
    const resolvedRoles = signOffRoles ?? collectSignatureRoles(container);
    const printedAt = new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    }).format(new Date());

    const html = buildPrintHtml({
        planName,
        patientProfile,
        sections,
        printedAt,
        signOffRoles: resolvedRoles,
    });

    const printWindow = window.open('', '_blank', 'noopener,noreferrer,width=900,height=700');
    if (!printWindow) {
        window.alert('Pop-up blocked. Allow pop-ups for this site to print the care plan.');
        return;
    }

    printWindow.document.open();
    printWindow.document.write(html);
    printWindow.document.close();

    printWindow.focus();
    const triggerPrint = () => {
        printWindow.print();
        printWindow.onafterprint = () => printWindow.close();
    };

    if (printWindow.document.readyState === 'complete') {
        triggerPrint();
    } else {
        printWindow.onload = triggerPrint;
    }
}
