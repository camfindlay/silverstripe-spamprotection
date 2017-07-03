<?php

/**
 * Editable Spam Protecter Field. Used with the User Defined Forms module (if
 * installed) to allow the user to have captcha fields with their custom forms
 *
 * @package spamprotection
 */
if (class_exists('EditableFormField')) {
    class EditableSpamProtectionField extends EditableFormField
    {

        private static $db = array(
            'SpamMapping' => 'Text',
        );

        private static $singular_name = 'Spam Protection Field';

        private static $plural_name = 'Spam Protection Fields';
        /**
         * Fields to include spam detection for
         *
         * @var array
         * @config
         */
        private static $check_fields = array(
            'EditableEmailField',
            'EditableTextField',
            'EditableNumericField'
        );

        /**
         * @var FormField
         */
        protected $formField = null;

        public function getFormField()
        {
            if ($this->formField) {
                return $this->formField;
            }

            // Get protector
            $protector = FormSpamProtectionExtension::get_protector();
            if (!$protector) {
                return false;
            }

            // Extract saved field mappings and update this field.
            $fieldMapping = array();
            foreach ($this->getCandidateFields() as $otherField) {
                $mapSetting = "Map-{$otherField->Name}";
                $spamField = $this->getSetting($mapSetting);
                $fieldMapping[$otherField->Name] = $spamField;
            }
            $protector->setFieldMapping($fieldMapping);

            // Generate field
            return $protector->getFormField($this->Name, $this->Title, null);
        }

        /**
         * @param FormField $field
         * @return self
         */
        public function setFormField(FormField $field)
        {
            $this->formField = $field;

            return $this;
        }

        /**
         * Gets the list of all candidate spam detectable fields on this field's form
         *
         * @return DataList
         */
        protected function getCandidateFields()
        {

            // Get list of all configured classes available for spam detection
            $types = self::config()->check_fields;
            $typesInherit = array();
            foreach ($types as $type) {
                $subTypes = ClassInfo::subclassesFor($type);
                $typesInherit = array_merge($typesInherit, $subTypes);
            }

            // Get all candidates of the above types
            return $this
                ->Parent()
                ->Fields()
                ->filter('ClassName', $typesInherit)
                ->exclude('Title', ''); // Ignore this field and those without titles
        }

        public function getCMSFields()
        {
            $fields = parent::getCMSFields();

            // Get protector
            $protector = FormSpamProtectionExtension::get_protector();
            if (!$protector) {
                return $fields;
            }

            // Generate field specific settings
            $mappableFields = Config::inst()->get('FormSpamProtectionExtension', 'mappable_fields');
            $mappableFieldsMerged = array_combine($mappableFields, $mappableFields);
            foreach ($this->getCandidateFields() as $field) {
                $fields->addFieldToTab(
                    'Root.SpamFieldMapping',
                    DropdownField::create('SpamMapping['.$field->Name.']', $field->Title, $mappableFieldsMerged)->setEmptyString('')
                );
            }
            return $fields;
        }

        public function onBeforeWrite()
        {
            parent::onBeforeWrite();
                $mapping = serialize($this->SpamMapping);
                $this->SpamMapping = $mapping;

        }

        /**
         * Using custom validateField method
         * as Spam Protection Field implementations may have their own error messages
         * and may not be based on the field being required, e.g. Honeypot Field
         * 
         * @param array $data
         * @param Form $form
         * @return void
         */
        public function validateField($data, $form) 
        {
            $formField = $this->getFormField();
            $formField->setForm($form);

            if (isset($data[$this->Name])) {
                $formField->setValue($data[$this->Name]);
            }

            $validator = $form->getValidator();
            if (!$formField->validate($validator)) {
                $errors = $validator->getErrors();
                $foundError = false;

                // field validate implementation may not add error to validator
                if (count($errors) > 0) {
                    // check if error already added from fields' validate method
                    foreach ($errors as $error) {
                        if ($error['fieldName'] == $this->Name) {
                            $foundError = $error;
                            break;
                        }
                    }
                }

                if ($foundError !== false) {
                    // use error messaging already set from validate method
                    $form->addErrorMessage($this->Name, $foundError['message'], $foundError['messageType'], false);
                } else {
                    // fallback to custom message set in CMS or default message if none set
                    $form->addErrorMessage($this->Name, $this->getErrorMessage()->HTML(), 'error', false);
                }
            }
        }

        public function getFieldValidationOptions()
        {
            return new FieldList();
        }

        public function getRequired()
        {
            return false;
        }

        public function getIcon()
        {
            return 'spamprotection/images/' . strtolower($this->class) . '.png';
        }

        public function showInReports()
        {
            return false;
        }
    }
}
