<?php

/*
|--------------------------------------------------------------------------
| CRM Language Lines
|--------------------------------------------------------------------------
|
| Strings for the CRM module. Enum labels (organisation unit types, social
| media) live in enums.php.
|
*/

return [
    'save_changes' => 'Save Changes',

    'address' => [
        'title' => 'Address',
        'description' => 'Leave every field blank for no address.',
        'postcode' => 'Postcode',
        'line_1' => 'Address line 1',
        'line_2' => 'Address line 2',
        'line_3' => 'Address line 3',
        'town_city' => 'Town / City',
        'county' => 'County',
        'country' => 'Country',
    ],

    'contacts' => [
        'columns' => [
            'name' => 'Name',
            'email' => 'Email',
            'telephone' => 'Telephone',
            'status' => 'Status',
        ],
        'fields' => [
            'title' => 'Title',
            'title_placeholder' => 'e.g. Dr',
            'first_name' => 'First name',
            'last_name' => 'Last name',
            'email' => 'Email',
            'telephone' => 'Telephone',
            'is_spam' => 'Spam',
            'is_spam_help' => 'Spam contacts are kept, but treated as not genuine.',
        ],
        'marketing' => [
            'title' => 'Marketing preferences',
            'description' => 'What the contact has agreed to be contacted about.',
            'marketing_email' => 'Email',
            'marketing_sms' => 'SMS',
            'marketing_telephone' => 'Telephone',
        ],
        'addresses' => [
            'title' => 'Addresses',
            'empty' => 'No addresses.',
            'add' => 'Add address',
            'remove' => 'Remove address',
        ],
        'status' => [
            'genuine' => 'Genuine',
            'spam' => 'Spam',
            'deleted' => 'Deleted',
        ],
        'filters' => [
            'search' => 'Search name, email or phone',
            'spam' => 'Spam',
            'any' => 'All contacts',
            'genuine_only' => 'Genuine only',
            'spam_only' => 'Spam only',
            'show_deleted' => 'Show deleted',
        ],
        'actions' => [
            'mark_spam' => 'Mark as spam',
            'not_spam' => 'Not spam',
            'restore' => 'Restore',
        ],
        'index' => [
            'title' => 'Contacts',
            'create' => 'New contact',
            'empty' => 'No contacts yet.',
        ],
        'create' => [
            'title' => 'Create Contact',
            'submit' => 'Create Contact',
        ],
        'edit' => [
            'title' => 'Edit Contact: :name',
        ],
        'show' => [
            'title' => 'Contact: :name',
        ],
        'validation' => [
            'first_name_required' => 'First name is required',
            'last_name_required' => 'Last name is required',
            'email_invalid' => 'Invalid email address',
            'email_taken' => 'A contact with this email already exists. If it was deleted, restore it from the deleted contacts instead.',
            'duplicate_address' => 'The same address has been added more than once.',
        ],
        'confirm_delete' => 'Are you sure you want to delete contact :email? It can be restored later.',
        'flash' => [
            'created' => 'Contact created successfully.',
            'updated' => 'Contact updated successfully.',
            'deleted' => 'Contact deleted successfully.',
            'restored' => 'Contact restored successfully.',
            'marked_spam' => 'Contact marked as spam.',
            'marked_not_spam' => 'Contact marked as not spam.',
        ],
    ],

    'forms' => [
        'columns' => [
            'name' => 'Name',
            'slug' => 'Slug',
            'fields' => 'Fields',
        ],
        'fields' => [
            'name' => 'Name',
            'slug' => 'Slug',
            'slug_help' => 'Websites use this to load the form. Leave blank to generate it from the name.',
            'email_recipients' => 'Notification emails',
            'email_recipients_help' => 'Who is emailed when the form is submitted.',
            'email_recipient_placeholder' => 'name@example.com',
            'add_email_recipient' => 'Add email',
            'remove_email_recipient' => 'Remove email',
            'submit_button_text' => 'Submit button text',
            'submit_button_text_placeholder' => 'Submit',
            'success_message' => 'Success message',
            'success_message_help' => 'Shown after the form is submitted, unless it redirects.',
            'redirect_url' => 'Redirect URL',
            'redirect_url_help' => 'Optional page to send people to after submitting.',
        ],
        'marketing' => [
            'title' => 'Marketing consent',
            'description' => 'Which marketing opt-ins the form asks for. Answers update the contact.',
            'marketing_email' => 'Email',
            'marketing_sms' => 'SMS',
            'marketing_telephone' => 'Telephone',
        ],
        'sections' => [
            'details' => 'Details',
            'settings' => 'Settings',
            'after_submit' => 'After submitting',
        ],
        'builder' => [
            'title' => 'Fields',
            'description' => 'Drag to reorder. Contact fields fill in the submitting contact\'s details.',
            'empty' => 'No fields yet. Add one to get started.',
            'add_field' => 'Field',
            'choose_field' => 'Choose a field to edit its settings.',
            'field_of_type' => ':type field',
            'duplicate' => 'Duplicate field',
            'copy_name' => ':name (copy)',
            'remove' => 'Remove field',
            'untitled' => 'Untitled field',
            'type' => 'Type',
            'name' => 'Label',
            'slug' => 'Key',
            'slug_help' => 'The name the answer is stored under. Leave blank to generate it from the label.',
            'is_required' => 'Required',
            'is_required_help' => 'The form can\'t be submitted without it.',
            'contact_field_help' => 'Fills in the submitting contact\'s details. A form can have one of each.',
            'type_settings' => ':type settings',
            'no_type_settings' => 'This field type has no extra settings.',
            'max_length' => 'Max length',
            'rows' => 'Rows',
            'min' => 'Minimum',
            'max' => 'Maximum',
            'type_groups' => [
                'standard' => 'Standard fields',
                'contact' => 'Contact details',
            ],
            'type_descriptions' => [
                'text' => 'One line of text',
                'textarea' => 'A few lines of text, e.g. a message',
                'number' => 'A number, with an optional minimum and maximum',
                'email' => 'An email address that isn\'t the contact\'s own',
                'hidden' => 'A value the website fills in, not shown to visitors',
                'crm_email' => 'The contact\'s email address',
                'crm_title' => 'The contact\'s title, e.g. Ms or Dr',
                'crm_first_name' => 'The contact\'s first name',
                'crm_last_name' => 'The contact\'s last name',
                'crm_telephone' => 'The contact\'s phone number',
            ],
        ],
        'index' => [
            'title' => 'Forms',
            'create' => 'New form',
            'empty' => 'No forms yet.',
        ],
        'create' => [
            'title' => 'Create Form',
            'submit' => 'Create Form',
        ],
        'edit' => [
            'title' => 'Edit Form: :name',
        ],
        'show' => [
            'title' => 'Form: :name',
        ],
        'validation' => [
            'duplicate_field_slug' => 'Each field needs its own key.',
            'reserved_field_slug' => 'This key is used by the form itself, so choose another.',
            'duplicate_contact_field' => 'Only one ":type" field can be added.',
            'max_below_min' => 'The maximum cannot be below the minimum.',
        ],
        'confirm_delete' => 'Are you sure you want to delete form :name?',
        'flash' => [
            'created' => 'Form created successfully.',
            'updated' => 'Form updated successfully.',
            'deleted' => 'Form deleted successfully.',
        ],
    ],

    'submissions' => [
        'columns' => [
            'submitted_at' => 'Submitted',
            'form' => 'Form',
            'contact' => 'Contact',
            'status' => 'Status',
        ],
        'status' => [
            'genuine' => 'Genuine',
            'spam' => 'Spam',
        ],
        'filters' => [
            'form' => 'All forms',
            'any' => 'All submissions',
            'genuine_only' => 'Genuine only',
            'spam_only' => 'Spam only',
            'from' => 'From',
            'to' => 'To',
            'contact' => 'Contact: :name',
            'clear_contact' => 'Show all contacts',
        ],
        'show' => [
            'title' => 'Submission',
            'details' => 'Details',
            'answers' => 'Answers',
            'no_answer' => 'No answer',
            'no_answers' => 'This submission has no answers.',
            'marketing' => 'Marketing consent',
            'marketing_none' => 'This form did not ask for marketing consent.',
            'consented' => 'Yes',
            'not_consented' => 'No',
            'no_contact' => 'No contact',
            'deleted' => '(deleted)',
        ],
        'contact_list' => [
            'title' => 'Submissions',
            'empty' => 'This contact has not submitted any forms.',
            'view_all' => 'View all',
        ],
        'actions' => [
            'mark_spam' => 'Mark as spam',
            'not_spam' => 'Not spam',
        ],
        'index' => [
            'title' => 'Submissions',
        ],
        'mail' => [
            'subject' => 'New submission: :form',
            'heading' => 'New :form submission',
            'intro' => 'The :form form was submitted on :date.',
            'question' => 'Question',
            'answer' => 'Answer',
            'view' => 'View submission',
        ],
        'api' => [
            'submitted' => 'Thank you, your form has been submitted.',
        ],
        'flash' => [
            'marked_spam' => 'Submission marked as spam.',
            'marked_not_spam' => 'Submission marked as not spam.',
        ],
    ],

    'organisation_units' => [
        'socials_summary' => '{1} 1 link|[2,*] :count links',
        'socials_none' => 'No links yet',
        'columns' => [
            'name' => 'Name',
            'type' => 'Type',
            'parent' => 'Part of',
            'email' => 'Email',
        ],
        'fields' => [
            'type' => 'Type',
            'parent' => 'Part of',
            'parent_help' => [
                'location' => 'A location is part of a company.',
                'department' => 'A department is part of a location.',
            ],
            'name' => 'Name',
            'slug' => 'Slug',
            'slug_help' => 'Used in URLs. Leave blank to generate it from the name.',
            'email' => 'Email',
            'telephone' => 'Telephone',
            'mobile_phone' => 'Mobile phone',
            'notification_emails' => 'Notification emails',
            'notification_emails_help' => 'Who is emailed about enquiries for this unit.',
            'notification_email_placeholder' => 'name@example.com',
            'add_notification_email' => 'Add email',
            'remove_notification_email' => 'Remove email',
            'parent_placeholder' => 'Choose…',
        ],
        'sections' => [
            'details' => 'Details',
            'contact' => 'Contact',
            'socials' => 'Social media',
            'structure' => 'Structure',
        ],
        'filters' => [
            'name' => 'Name',
            'type' => 'Type',
        ],
        'index' => [
            'title' => 'Organisation Units',
            'create' => 'New organisation unit',
            'empty' => 'No organisation units yet.',
        ],
        'create' => [
            'title' => 'Create Organisation Unit',
            'submit' => 'Create Organisation Unit',
        ],
        'edit' => [
            'title' => 'Edit Organisation Unit: :name',
        ],
        'show' => [
            'title' => 'Organisation Unit: :name',
        ],
        'validation' => [
            'name_required' => 'Name is required',
            'email_invalid' => 'Invalid email address',
            'parent_type' => 'A :type must be part of a :parents.',
            'type_has_children' => 'The type cannot be changed while other units are part of this one.',
            'has_children' => 'This organisation unit still has units within it. Move or delete those first.',
        ],
        'confirm_delete' => 'Are you sure you want to delete organisation unit :name?',
        'flash' => [
            'created' => 'Organisation unit created successfully.',
            'updated' => 'Organisation unit updated successfully.',
            'deleted' => 'Organisation unit deleted successfully.',
        ],
    ],
];
