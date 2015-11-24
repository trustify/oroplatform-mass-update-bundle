<?php

namespace Trustify\Bundle\MassUpdateBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;

use Oro\Bundle\EntityBundle\Form\Type\EntityFieldChoiceType;
use Trustify\Bundle\MassUpdateBundle\Form\Type\GuessFieldType;

class MassUpdateController extends Controller
{
    /**
     * @Route("/", name="trustify_mass_update")
     * @Template
     *
     * @param Request $request
     *
     * @return array
     */
    public function massUpdateAction(Request $request)
    {
        $entityName    = str_replace('_', '\\', $request->get('entityName'));
        $selectedField = $request->get('selectedFormField');

        if ($selectedField) {
            // TODO: perform ACL check: if entity is allowed to be viewed

            // TODO: implement field filter to allow only plain text/date/int fields and many-to-one rels
            // otherwise it's hard to guess field type
            // or fallback to plain text field

            // TODO: add settings to enable/attach to some grid
            // TODO: develop isApplied check to bypass grids that doesn't have all the params


            $form = $this->get('form.factory')->create(
                GuessFieldType::NAME,
                null,
                ['data_class' => $entityName, 'field_name' => $selectedField]
            );

            // TODO: check why it's building all the fields
            $form = $form->get($selectedField);

        } else {
            $form = $this->get('form.factory')->createNamed(
                'mass_edit_field',
                EntityFieldChoiceType::NAME,
                null,
                [
                    'entity'         => $entityName,
                    'with_relations' => true
                ]
            );
        }

        return [
            'form'          => $form->createView(),
            'selectedField' => $selectedField,
        ];
    }
}
