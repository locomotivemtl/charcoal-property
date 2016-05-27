<?php

namespace Charcoal\Property;

use \Charcoal\Translation\TranslationConfig;

use \Charcoal\Property\PropertyField;

/**
 *
 */
trait StorablePropertyTrait
{
    /**
     * @return array
     */
    public function fields()
    {
        $fields = [];
        if ($this->l10n()) {
            $translator = TranslationConfig::instance();

            foreach ($translator->availableLanguages() as $langCode) {
                $ident = sprintf('%1$s_%2$s', $this->ident(), $langCode);
                $field = new PropertyField();
                $field->setData(
                    [
                        'ident'      => $ident,
                        'sqlType'    => $this->sqlType(),
                        'sqlPdoType' => $this->sqlPdoType(),
                        'extra'      => $this->sqlExtra(),
                        'val'        => $this->fieldVal($langCode),
                        'defaultVal' => null,
                        'allowNull'  => $this->allowNull(),
                        'comment'    => $this->label()
                    ]
                );
                $fields[$langCode] = $field;
            }
        } else {
            $field = new PropertyField();
            $field->setData(
                [
                    'ident'      => $this->ident(),
                    'sqlType'    => $this->sqlType(),
                    'sqlPdoType' => $this->sqlPdoType(),
                    'extra'      => $this->sqlExtra(),
                    'val'        => $this->storageVal(),
                    'defaultVal' => null,
                    'allowNull'  => $this->allowNull(),
                    'comment'    => $this->label()
                ]
            );
            $fields[] = $field;
        }

        return $fields;
    }

    /**
     * @param string $fieldIdent The property field identifier.
     * @return mixed
     */
    public function fieldVal($fieldIdent)
    {
        $val = $this->val();

        if ($val === null) {
            return null;
        }
        if (is_scalar($val)) {
            return $this->storageVal($val);
        }
        if (isset($val[$fieldIdent])) {
            return $this->storageVal($val[$fieldIdent]);
        } else {
            return null;
        }
    }

    /**
     * Get the property's value in a format suitable for storage.
     *
     * @param mixed $val Optional. The value to convert to storage value.
     * @return mixed
     */
    public function storageVal($val = null)
    {
        if ($val === null) {
            $val = $this->val();
        }
        if ($val === null) {
            // Do not json_encode NULL values
            return null;
        }

        if ($this->multiple()) {
            if (is_array($val)) {
                $val = implode($this->multipleSeparator(), $val);
            }
        }

        if (!is_scalar($val)) {
            return json_encode($val, true);
        }
        return $val;
    }

    /**
     * @return string
     */
    abstract public function sqlExtra();

    /**
     * @return string
     */
    abstract public function sqlType();

    /**
     * @return integer
     */
    abstract public function sqlPdoType();
}