<?php

namespace Charcoal\Property;

use \PDO;

use \Charcoal\Translation\TranslationConfig;

use \Charcoal\Property\SelectablePropertyInterface;
use \Charcoal\Property\SelectablePropertyTrait;

/**
 * Language property
 */
class LangProperty extends AbstractProperty implements SelectablePropertyInterface
{
    use SelectablePropertyTrait;

    /**
     * @return string
     */
    public function type()
    {
        return 'lang';
    }

    /**
     * @return string
     */
    public function sqlExtra()
    {
        return '';
    }

    /**
     * Get the SQL type (Storage format)
     *
     * @return string The SQL type
     * @todo   Only the 2-character language code (ISO 639-1)
     */
    public function sqlType()
    {
        if ($this->multiple()) {
            return 'TEXT';
        }
        return 'CHAR(2)';
    }

    /**
     * @return integer
     */
    public function sqlPdoType()
    {
        return PDO::PARAM_BOOL;
    }

    /**
     * @return array
     */
    public function choices()
    {
        $translator = TranslationConfig::instance();

        $choices = [];
        foreach ($translator->languages() as $langCode => $langObj) {
            $label = (string)$langObj->name();
            if (empty($label)) {
                $label = (string)$langObj;
            }
            $choices[] = [
                'label'    => $label,
                'selected' => ($this->val() === $langCode),
                'value'    => $langCode
            ];
        }

        return $choices;
    }

    /**
     * @return mixed
     */
    public function save()
    {
        return $this->val();
    }
}