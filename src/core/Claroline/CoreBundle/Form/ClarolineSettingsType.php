<?php

namespace Claroline\CoreBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilder;

class ClarolineSettingsType extends AbstractType
{
    public function buildForm(FormBuilder $builder, array $options)
    {
        $builder->add('selfRegistration', 'checkbox', array('required' => false));
        $builder->add('localLanguage', 'choice', array(
            'choices' => array('en' => 'en', 'fr' => 'fr')
            )
        );
    }

    public function getName()
    {
        return 'claro_settings_form';
    }
}