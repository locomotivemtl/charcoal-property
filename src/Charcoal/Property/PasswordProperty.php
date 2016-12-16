<?php

namespace Charcoal\Property;

// Local namespace dependencies
use \Charcoal\Property\StringProperty;

/**
 * Password Property
 *
 * The password property is a specialized string property meant to store encrypted passwords.
 */
class PasswordProperty extends StringProperty
{
    /**
     * @return string
     */
    public function type()
    {
        return 'password';
    }

    /**
     * @param mixed $val The value, at time of saving.
     * @return string
     */
    public function save($val)
    {
        $password = $val;

        // Assuming the password_needs_rehash is set to true is the hash given isn't a hash
        if (password_needs_rehash($password, PASSWORD_DEFAULT)) {
            $val = password_hash($password, PASSWORD_DEFAULT);
        }

        return $val;
    }
}