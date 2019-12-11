import MailPoet from 'mailpoet';

export const name = 'mailpoet-form/custom-text';

export function getSettings(customField) {
  return {
    title: customField.name,
    description: '',
    icon: 'admin-site', // TODO
    category: 'custom-fields',
    attributes: {
      label: {
        type: 'string',
        default: MailPoet.I18n.t('blockFirstName'),
      },
      labelWithinInput: {
        type: 'boolean',
        default: true,
      },
      mandatory: {
        type: 'boolean',
        default: false,
      },
    },
    supports: {
      html: false,
      customClassName: false,
      multiple: false,
    },
    edit() {
      return null;
    }, // TODO
    save() {
      return null;
    },
  };
};
