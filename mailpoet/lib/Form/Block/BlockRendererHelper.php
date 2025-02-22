<?php

namespace MailPoet\Form\Block;

use MailPoet\Form\Util\FieldNameObfuscator;
use MailPoet\Models\ModelValidator;
use MailPoet\WP\Functions as WPFunctions;

/**
 * This class still covers several responsibilities and could be further refactored
 * @package MailPoet\Form\Block
 */
class BlockRendererHelper {

  /** @var FieldNameObfuscator */
  private $fieldNameObfuscator;

  /** @var WPFunctions */
  protected $wp;

  public function __construct(
    FieldNameObfuscator $fieldNameObfuscator,
    WPFunctions $wp
  ) {
    $this->fieldNameObfuscator = $fieldNameObfuscator;
    $this->wp = $wp;
  }

  public function getInputValidation(array $block, array $extraRules = [], ?int $formId = null): string {
    $rules = [];
    $blockId = $this->wp->escAttr($block['id']);

    if ($blockId === 'email') {
      $rules['required'] = true;
      $rules['minlength'] = ModelValidator::EMAIL_MIN_LENGTH;
      $rules['maxlength'] = ModelValidator::EMAIL_MAX_LENGTH;
      $rules['error-message'] = __('Please specify a valid email address.', 'mailpoet');
    }

    if (($blockId === 'first_name') || ($blockId === 'last_name')) {
      $errorMessages = [
        __('Please specify a valid name.', 'mailpoet'),
        __('Addresses in names are not permitted, please add your name instead.', 'mailpoet'),
      ];
      $rules['names'] = '[' . implode(',', array_map(function (string $errorMessage): string {
        return htmlspecialchars((string)json_encode($errorMessage), ENT_QUOTES);
      }, $errorMessages)) . ']';
    }

    // Segments should be required only when form ID is not empty. That allows save form on subscription management site when any segment is not checked.
    if ($blockId === 'segments' && $formId) {
      $rules['required'] = true;
      $rules['mincheck'] = 1;
      $rules['group'] = $blockId;
      $rules['errors-container'] = '.mailpoet_error_' . $blockId . '_' . $formId;
      $rules['required-message'] = __('Please select a list.', 'mailpoet');
    }

    if (!empty($block['params']['required'])) {
      $rules['required'] = true;
      $rules['required-message'] = __('This field is required.', 'mailpoet');
    }

    if (!empty($block['params']['validate'])) {
      if ($block['params']['validate'] === 'phone') {
        $rules['pattern'] = "^[\d\+\-\.\(\)\/\s]*$";
        $rules['error-message'] = __('Please specify a valid phone number.', 'mailpoet');
      } else {
        $rules['type'] = $this->wp->escAttr($block['params']['validate']);
      }
    }

    if (in_array($block['type'], ['radio', 'checkbox'])) {
      $rules['group'] = 'custom_field_' . $blockId;
      $rules['errors-container'] = '.mailpoet_error_' . $blockId . ($formId ? '_' . $formId : '');
      $rules['required-message'] = __('Please select at least one option.', 'mailpoet');
    }

    if ($block['type'] === 'date') {
      $rules['group'] = 'custom_field_' . $blockId;
      $rules['errors-container'] = '.mailpoet_error_' . $blockId . ($formId ? '_' . $formId : '');
    }

    $validation = [];

    $rules = array_merge($rules, $extraRules);

    if (!empty($rules)) {
      $rules = array_unique($rules);
      foreach ($rules as $rule => $value) {
        if (is_bool($value)) {
          $value = ($value) ? 'true' : 'false';
        }
        // We need to use single quotes because we need to pass array of strings as a parameter for custom validation
        if ($rule === 'names') {
          $validation[] = 'data-parsley-' . $rule . '=\'' . $value . '\'';
        } else {
          $validation[] = 'data-parsley-' . $rule . '="' . $value . '"';
        }
      }
    }
    return join(' ', $validation);
  }

  public function renderLabel(array $block, array $formSettings): string {
    $html = '';
    if (
      isset($block['params']['hide_label'])
      && $block['params']['hide_label']
    ) {
      return $html;
    }
    if (
      isset($block['params']['label_within'])
      && $block['params']['label_within']
    ) {
      return $html;
    }
    $automationId = null;
    if (in_array($block['id'], ['email', 'last_name', 'first_name'], true)) {
      $automationId = 'data-automation-id="form_' . $block['id'] . '_label" ';
    }
    if (
      isset($block['params']['label'])
      && strlen(trim($block['params']['label'])) > 0
    ) {
      $html .= '<label '
        . 'class="mailpoet_' . $block['type'] . '_label" '
        . $this->renderFontStyle($formSettings, $block['styles'] ?? [])
        . ($automationId ?? '')
        . '>';
      $html .= htmlspecialchars($block['params']['label']);

      if (isset($block['params']['required']) && $block['params']['required']) {
        $html .= ' <span class="mailpoet_required">*</span>';
      }

      $html .= '</label>';
    }
    return $html;
  }

  public function renderFontStyle(array $formSettings, array $styles = []) {
    $rules = [];
    if (isset($formSettings['fontSize'])) {
      $rules[] = 'font-size: ' . trim($formSettings['fontSize']) . 'px;';
      $rules[] = 'line-height: ' . (float)trim($formSettings['fontSize']) * 1.2 . 'px";';
    }
    if (isset($styles['bold'])) {
      $rules[] = 'font-weight: bold;';
    }
    return $rules ? 'style="' . $this->wp->escAttr(implode("", $rules)) . '"' : '';
  }

  public function renderInputPlaceholder(array $block): string {
    $html = '';
    // if the label is displayed as a placeholder,
    if (
      isset($block['params']['label_within'])
      && $block['params']['label_within']
    ) {
      // display only label
      $html .= ' placeholder="';
      $html .= static::getFieldLabel($block);
      // add an asterisk if it's a required field
      if (isset($block['params']['required']) && $block['params']['required']) {
        $html .= ' *';
      }
      $html .= '" ';
    }
    return $html;
  }

  // return field name depending on block data
  public function getFieldName(array $block = []): string {
    if ((int)$block['id'] > 0) {
      return 'cf_' . $block['id'];
    } elseif (isset($block['params']['obfuscate']) && !$block['params']['obfuscate']) {
      return $block['id'];
    } else {
      return $this->fieldNameObfuscator->obfuscate($block['id']);//obfuscate field name for spambots
    }
  }

  public function getFieldLabel(array $block = []): string {
    return (isset($block['params']['label'])
            && strlen(trim($block['params']['label'])) > 0)
            ? $this->wp->escHtml(trim($block['params']['label'])) : '';
  }

  public function getFieldValue($block = []) {
    return (isset($block['params']['value'])
            && strlen(trim($block['params']['value'])) > 0)
            ? $this->wp->escAttr(trim($block['params']['value'])) : '';
  }

  public function getFieldIsRequired($block = []): bool {
    return (isset($block['params']['required'])
            && strlen(trim($block['params']['required'])) > 0)
            ? !empty($block['params']['required']) : false;
  }

  public function getInputModifiers(array $block = []): string {
    $modifiers = [];

    if (isset($block['params']['readonly']) && $block['params']['readonly']) {
      $modifiers[] = 'readonly';
    }

    if (isset($block['params']['disabled']) && $block['params']['disabled']) {
      $modifiers[] = 'disabled';
    }
    return join(' ', $modifiers);
  }
}
