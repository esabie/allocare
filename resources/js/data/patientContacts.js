const defaultContacts = {
    profile: {
        name: 'Sarah Jenkins',
        dob: '14-05-1942',
        nhs: '485 922 1039',
        urgentTag: 'Urgent Care',
    },
    keyContacts: {
        nextOfKin: {
            name: 'Mark Jenkins',
            phone: '+44 7700 900123',
            relation: 'Son / Next of Kin',
        },
        emergency: {
            name: 'Alice Jenkins',
            phone: '+44 7700 900456',
            relation: 'Daughter',
        },
    },
    personal: [
        { initials: 'MJ', name: 'Mark Jenkins', role: 'Son / Next of Kin', badge: 'Primary', phone: '+44 7700 900123', email: 'mark.j@email.com' },
        { initials: 'AJ', name: 'Alice Jenkins', role: 'Daughter', badge: '', phone: '+44 7700 900456', email: 'alice.j@provider.co.uk' },
    ],
    professional: [
        { initials: 'DR', name: 'Dr. Robert Chen', role: 'General Practitioner (GP)', badge: 'Clinical Lead', phone: '+44 20 7946 0122', email: 'r.chen@nhs.net' },
        { initials: 'LM', name: 'Linda Murray', role: 'Lead Pharmacist', badge: '', phone: '+44 20 7946 0888', email: '440-221-PHRM' },
    ],
};

const contactsByPatient = {
    'sarah-jenkins': defaultContacts,
    'cr-88210': defaultContacts,
};

export function getPatientContacts(identifier) {
    return contactsByPatient[identifier] || defaultContacts;
}

