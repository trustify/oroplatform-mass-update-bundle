<?php

namespace Trustify\Bundle\MassUpdateBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Acl\Voter\FieldVote;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;

use Trustify\Bundle\MassUpdateBundle\Form\Type\GuessFieldType;
use Trustify\Bundle\MassUpdateBundle\Form\Type\UpdateFieldChoiceType;

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

        if (!$this->get('oro_security.security_facade')->isGranted('EDIT', 'entity:' . $entityName)) {
            throw new AccessDeniedException();
        }

        // form submission handled by Oro\Bundle\DataGridBundle\Controller\GridController::massActionAction
        $formFactory = $this->get('form.factory');
        if ($selectedField) {
            $this->checkFieldAccess($entityName, $selectedField);

            $form = $formFactory->createNamed(
                '',
                GuessFieldType::NAME,
                null,
                [
                    'data_class'              => $entityName,
                    'field_name'              => $selectedField,
                    'dynamic_fields_disabled' => true,
                    'ownership_disabled'      => true
                ]
            );
        } else {
            $form = $formFactory->createNamed(
                'mass_edit_field',
                UpdateFieldChoiceType::NAME,
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

    /**
     * @param string $entityName
     * @param string $fieldName
     *
     * @throws AccessDeniedException
     */
    protected function checkFieldAccess($entityName, $fieldName)
    {
        if (!$this->get('oro_config.manager')->get('trustify_mass_update.field_acl_enabled')) {
            return;
        }

        if (!$this->get('oro_security.security_facade')->isGranted(
            'EDIT',
            new FieldVote(
                $this->get('oro_entity.doctrine_helper')->createEntityInstance($entityName),
                $fieldName
            )
        )
        ) {
            throw new AccessDeniedException();
        }
    }
}
