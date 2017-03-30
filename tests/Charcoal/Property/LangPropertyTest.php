<?php

namespace Charcoal\Tests\Property;

use PDO;
use ReflectionClass;

// From 'symfony/translator'
use Symfony\Component\Translation\Loader\ArrayLoader;

// From 'charcoal-translator'
use Charcoal\Translator\Translator;
use Charcoal\Translator\LocalesManager;

// From 'charcoal-property'
use Charcoal\Property\LangProperty;

/**
 * Lang Property Test
 */
class LangPropertyTest extends \PHPUnit_Framework_TestCase
{
    use \Charcoal\Tests\Property\ContainerIntegrationTrait;

    /**
     * Tested Class.
     *
     * @var LangProperty
     */
    public $obj;

    /**
     * Store the translator service.
     *
     * @var Translator
     */
    private $translator;

    /**
     * Set up the test.
     */
    public function setUp()
    {
        $container = $this->getContainer();

        $this->obj = new LangProperty([
            'container'  => $container,
            'database'   => $container['database'],
            'logger'     => $container['logger'],
            'translator' => $this->translator()
        ]);
    }

    private function translator()
    {
        if ($this->translator === null) {
            $this->translator = new Translator([
                'manager' => new LocalesManager([
                    'locales' => [
                        'en'  => [
                            'locale' => 'en-US',
                            'name'   => [
                                'en' => 'English',
                                'fr' => 'Anglais',
                                'es' => 'Inglés'
                            ]
                        ],
                        'fr' => [
                            'locale' => 'fr-CA',
                            'name'   => [
                                'en' => 'French',
                                'fr' => 'Français',
                                'es' => 'Francés'
                            ]
                        ],
                        'de' => [
                            'locale' => 'de-DE'
                        ],
                        'es' => [
                            'locale' => 'es-MX'
                        ]
                    ],
                    'default_language'   => 'en',
                    'fallback_languages' => [ 'en' ]
                ])
            ]);

            $this->translator->addLoader('array', new ArrayLoader());
            $this->translator->addResource('array', [ 'locale.de' => 'German'   ], 'en', 'messages');
            $this->translator->addResource('array', [ 'locale.de' => 'Allemand' ], 'fr', 'messages');
            $this->translator->addResource('array', [ 'locale.de' => 'Deutsch'  ], 'es', 'messages');
            $this->translator->addResource('array', [ 'locale.de' => 'Alemán'   ], 'de', 'messages');
        }

        return $this->translator;
    }

    public function testType()
    {
        $this->assertEquals('lang', $this->obj->type());
    }

    public function testSqlExtra()
    {
        $this->assertEquals('', $this->obj->sqlExtra());
    }

    public function testSqlType()
    {
        $this->obj->setMultiple(false);
        $this->assertEquals('CHAR(2)', $this->obj->sqlType());

        $this->obj->setMultiple(true);
        $this->assertEquals('TEXT', $this->obj->sqlType());
    }

    public function testSqlPdoType()
    {
        $this->assertEquals(PDO::PARAM_BOOL, $this->obj->sqlPdoType());
    }

    public function testChoices()
    {
        $this->assertTrue($this->obj->hasChoices());

        $locales = $this->translator()->locales();
        $choices = $this->obj->choices();

        $this->assertEquals(array_keys($choices), array_keys($this->obj->choices()));

        $this->obj->addChoice('zz', 'en');
        $this->assertEquals(array_keys($choices), array_keys($this->obj->choices()));

        $this->obj->addChoices([ 'zz' => 'en' ]);
        $this->obj->setChoices([ 'zz' => 'en' ]);
    }

    public function testDisplayVal()
    {
        $this->assertEquals('', $this->obj->displayVal(null));
        $this->assertEquals('', $this->obj->displayVal(''));

        $this->assertEquals('English', $this->obj->displayVal('en'));
        $this->assertEquals('Anglais', $this->obj->displayVal('en', [ 'lang' => 'fr' ]));

        $val = $this->translator()->translation('en');
        /** Test translatable value with a unilingual property */
        $this->assertEquals('English', $this->obj->displayVal($val));

        /** Test translatable value with a multilingual property */
        $this->obj->setL10n(true);
        $this->assertEquals('',        $this->obj->displayVal($val, [ 'lang' => 'ja' ]));
        $this->assertEquals('Inglés',  $this->obj->displayVal($val, [ 'lang' => 'es' ]));
        $this->assertEquals('Anglais', $this->obj->displayVal($val, [ 'lang' => 'fr' ]));
        $this->assertEquals('English', $this->obj->displayVal($val, [ 'lang' => 'de' ]));
        $this->assertEquals('English', $this->obj->displayVal($val));

        $this->obj->setL10n(false);
        $this->obj->setMultiple(true);
        $this->assertEquals('English, French, ES',   $this->obj->displayVal([ 'en', 'fr', 'es' ]));
        $this->assertEquals('Anglais, Français, ES', $this->obj->displayVal('en,fr,es', [ 'lang' => 'fr' ]));
        $this->assertEquals('Inglés, Francés, ES',   $this->obj->displayVal('en,fr,es', [ 'lang' => 'es' ]));
    }
}
