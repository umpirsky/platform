<?php

namespace Oro\Bundle\EntityExtendBundle\Controller;

use Oro\Bundle\EntityConfigBundle\Config\FieldConfig;
use Oro\Bundle\EntityConfigBundle\Entity\ConfigEntity;

use Oro\Bundle\EntityConfigBundle\Provider\ConfigProvider;
use Oro\Bundle\EntityExtendBundle\Form\Type\UniqueKeyCollectionType;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\Form\FormError;

/**
 * Class ConfigGridController
 * @package Oro\Bundle\EntityExtendBundle\Controller
 * @Route("/entityextend/entity")
 */
class ConfigEntityGridController extends Controller
{
    /**
     * @Route("/unique-key/{id}", name="oro_entityextend_entity_unique_key", requirements={"id"="\d+"}, defaults={"id"=0})
     * @Template
     */
    public function uniqueAction(ConfigEntity $entity)
    {
        /** @var ConfigProvider $configProvider */
        $configProvider = $this->get('oro_entity_extend.config.extend_config_provider');
        $entityConfig   = $configProvider->getConfig($entity->getClassName());

        $data = $entityConfig->has('unique_key') ? unserialize($entityConfig->get('unique_key')) : array() ;

        $request = $this->getRequest();
        $form    = $this->createForm(new UniqueKeyCollectionType($entityConfig->getFields(function (FieldConfig $fieldConfig) {
            return $fieldConfig->getType() != 'ref-many';
        })), $data);

        if ($request->getMethod() == 'POST') {
            $form->bind($request);

            if ($form->isValid()) {
                $data = $form->getData();

                $error = false;
                $names = array();
                foreach($data['keys'] as $key){
                    if (in_array($key['name'], $names)) {
                        $error = true;
                        $form->addError(new FormError(sprintf('Name for key should be unique, key "%s" is not unique.', $key['name'])));
                        break;
                    }

                    $names[] = $key['name'];
                }

                if (!$error) {
                    $entityConfig->set('unique_key', serialize($form->getData()));
                    $configProvider->persist($entityConfig);
                    $configProvider->flush();

                    return $this->redirect($this->generateUrl('oro_entityconfig_view', array('id'=> $entity->getId())));
                }
            }
        }

        return array(
            'form'      => $form->createView(),
            'entity_id' => $entity->getId()
        );
    }
}