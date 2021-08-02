<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 *
 * (c) 2021
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Form\Decorator;

use function count;
use function in_array;

abstract class AbstractFormDefinitionDecorator implements DefinitionDecoratorInterface
{
    var $TYPES = [
        'StaticText' => 'staticText',
        'Text' => 'text',
        'Textarea' => 'textarea',
        'Password' => 'password',
        'Email' => 'email',
        'Telephone' => 'tel',
        'Url' => 'url',
        'Number' => 'number',
        'Date' => 'date',
        'SingleSelect' => 'select',
        'FileUpload' => 'file',
        'Checkbox' => 'checkbox',
        'MultiCheckbox' => 'checkbox',
        'RadioButton' => 'radio'
    ];

    var $VALIDATIONS = [
        'EmailAddress' => 'email',
        'NotEmpty' => 'required',
        'Number' => 'number'
    ];

    /**
     * @var array<string, mixed>
     */
    protected $formStatus;

    public function __construct(array $formStatus = [])
    {
        $this->formStatus = $formStatus;
    }

    /**
     * @param array<mixed> $definition
     * @return array<string,array<mixed>>
     */
    public function __invoke(array $definition, int $currentPage): array
    {
        $decorated = [];

        $elements = $definition['renderables'] ?? [];
        $formId = $definition['identifier'];

        $decorated['id'] = $formId;
        $decorated['api'] = $this->formStatus;
        $decorated['i18n'] = $definition['i18n']['properties'] ?? [];
        $decorated['renderingOptions'] = $definition['renderingOptions'];
        $decorated['elements'] = $this->handleRenderables($elements, $formId);

        return $this->overrideDefinition($decorated, $definition, $currentPage);
    }

    /**
     * @param array<string, mixed> $renderables
     * @param string $formId
     * @return array<string, mixed>
     */
    protected function handleRenderables(array $renderables, string $formId): array
    {
        foreach ($renderables as &$element) {
            if (
                in_array($element['type'], ['Page', 'Fieldset', 'GridRow'], true) &&
                is_array($element['renderables']) &&
                count($element['renderables'])
            ) {
                $element['elements'] = $this->handleRenderables($element['renderables'], $formId);
                unset($element['renderables']);
                unset($element['defaultValue']);
                unset($element['properties']);
            } else {
                $element = $this->prepareElement($element, $formId);
            }
        }

        return $renderables;
    }

    /**
     * @param array<string, mixed> $element
     * @param string $formId
     * @return array<string, mixed>
     */
    protected function prepareElement(array $element, string $formId): array
    {

        $element['name'] = 'tx_form_formframework[' . $formId . '][' . $element['identifier'] . ']';

        $element = $this->overrideElement($element);

        if (isset($element['label'])) {
            if ($element['label'] === "") {
                unset($element['label']);
            }
        }

        $element['id'] = $element['identifier'];
        $element['validationName'] = $element['label'] ?? $element['identifier'];

        if (isset($element['defaultValue'])) {
            if ($element['defaultValue'] !== "") {
                $element['value'] = $element['defaultValue'];
            }
        }

        if (in_array($element['type'], ['ImageUpload', 'FileUpload'])) {
            unset($element['properties']['saveToFileMount']);
        }

        $element['type'] = $this->TYPES[$element['type']] ?? 'hidden';

        if (isset($element['properties'])) {
            $prop = $element['properties'];

            if (isset($prop['options'])) {
                $element['options'] = $prop['options'];
            }

            if (isset($prop['elementDescription'])) {
                if ($prop['elementDescription'] !== "") {
                    $element['help'] = $prop['elementDescription'];
                }
            }

            if (isset($prop['fluidAdditionalAttributes']['placeholder'])) {
                $element['placeholder'] = $prop['fluidAdditionalAttributes']['placeholder'];
            } else if (isset($prop['prependOptionLabel'])) {
                $element['placeholder'] = $prop['prependOptionLabel'];
            }

            if (isset($prop['allowedMimeTypes'])) {
                if (!isset($element['validators'])) $element['validators'] = [];
                $element['validators'][] = [
                    'identifier' => 'mime:' + join(',', $prop['allowedMimeTypes'])
                ];
            }

            unset($element['properties']);
        }

        if (isset($element['validators'])) {
            $element['validation'] = join(
                '|',
                array_map(
                    function ($item) {
                        return $this->VALIDATIONS[$item['identifier']] ?? $item['identifier'];
                    },
                    $element['validators']
                )
            );
            unset($element['validators']);
        }

        unset($element['identifier']);
        unset($element['defaultValue']);

        return $element;
    }

    /**
     * @param array<string, mixed> $element
     * @return array<string, mixed>
     */
    protected function overrideElement(array $element): array
    {
        return $element;
    }

    /**
     * @param array<string, mixed> $decorated
     * @param array<string, mixed> $definition
     * @param int $currentPage
     * @return array<string, mixed>
     */
    protected function overrideDefinition(array $decorated, array $definition, int $currentPage): array
    {
        return $decorated;
    }
}
