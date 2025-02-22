import { useContext } from 'react';
import { MailPoet } from 'mailpoet';
import { useAction, useSelector, useSetting } from 'settings/store/hooks';
import { GlobalContext } from 'context';
import { Button } from 'common/button/button';
import { t } from 'common/functions';
import { Input } from 'common/form/input/input';
import { KeyActivationState, MssStatus } from 'settings/store/types';
import { Inputs, Label } from 'settings/components';
import { SetFromAddressModal } from 'common/set_from_address_modal';
import {
  KeyMessages,
  MssMessages,
  PremiumMessages,
  ServiceUnavailableMessage,
} from './messages';

type KeyState = {
  is_approved: boolean;
};

function Messages(
  state: KeyActivationState,
  showPendingApprovalNotice: boolean,
  activationCallback: () => Promise<void>,
) {
  if (state.code === 503) {
    return (
      <div className="key-activation-messages">
        <ServiceUnavailableMessage />
      </div>
    );
  }

  return (
    <div className="key-activation-messages">
      <KeyMessages />
      {state.mssStatus !== null && (
        <MssMessages
          keyMessage={state.mssMessage}
          activationCallback={activationCallback}
        />
      )}
      {state.congratulatoryMssEmailSentTo && (
        <div className="mailpoet_success_item mailpoet_success">
          {t('premiumTabCongratulatoryMssEmailSent').replace(
            '[email_address]',
            state.congratulatoryMssEmailSentTo,
          )}
        </div>
      )}
      {state.premiumStatus !== null && (
        <PremiumMessages keyMessage={state.premiumMessage} />
      )}

      {showPendingApprovalNotice && (
        <div className="mailpoet_success">
          <div className="pending_approval_heading">
            {t('premiumTabPendingApprovalHeading')}
          </div>
          <div>{t('premiumTabPendingApprovalMessage')}</div>
        </div>
      )}

      {!state.isKeyValid && (
        <p>
          <a
            href="https://kb.mailpoet.com/article/319-known-errors-when-validating-a-mailpoet-key"
            target="_blank"
            rel="noopener noreferrer"
            data-beacon-article="5ef1da9d2c7d3a10cba966c5"
            className="mailpoet_error"
          >
            {MailPoet.I18n.t('learnMore')}
          </a>
        </p>
      )}
    </div>
  );
}

export function KeyActivation() {
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const { notices } = useContext<any>(GlobalContext);
  const state = useSelector('getKeyActivationState')();
  const setState = useAction('updateKeyActivationState');
  const verifyMssKey = useAction('verifyMssKey');
  const verifyPremiumKey = useAction('verifyPremiumKey');
  const sendCongratulatoryMssEmail = useAction('sendCongratulatoryMssEmail');
  const [senderAddress, setSenderAddress] = useSetting('sender', 'address');
  const [unauthorizedAddresses, setUnauthorizedAddresses] = useSetting(
    'authorized_emails_addresses_check',
  );
  const [apiKeyState] = useSetting('mta', 'mailpoet_api_key_state', 'data');
  const setAuthorizedAddress = async (address: string) => {
    await setSenderAddress(address);
    await setUnauthorizedAddresses(null);
  };

  const showFromAddressModal =
    state.fromAddressModalCanBeShown &&
    state.mssStatus === MssStatus.VALID_MSS_ACTIVE &&
    (!senderAddress || unauthorizedAddresses);

  const showPendingApprovalNotice =
    state.inProgress === false &&
    state.mssStatus === MssStatus.VALID_MSS_ACTIVE &&
    apiKeyState &&
    (apiKeyState as KeyState).is_approved === false;

  const verifyKey = async () => {
    if (!state.key) {
      notices.error(<p>{t('premiumTabNoKeyNotice')}</p>, { scroll: true });
      return;
    }
    await setState({
      mssStatus: null,
      premiumStatus: null,
      premiumInstallationStatus: null,
    });
    MailPoet.Modal.loading(true);
    setState({ inProgress: true });
    await verifyMssKey(state.key);
    await sendCongratulatoryMssEmail();
    await verifyPremiumKey(state.key);
    setState({ inProgress: false });
    MailPoet.Modal.loading(false);
    setState({ fromAddressModalCanBeShown: true });
  };

  async function activationCallback() {
    await verifyMssKey(state.key);
    sendCongratulatoryMssEmail();
    setState({ fromAddressModalCanBeShown: true });
  }

  return (
    <div className="mailpoet-settings-grid">
      <Label
        htmlFor="mailpoet_premium_key"
        title={t('premiumTabActivationKeyLabel')}
        description={t('premiumTabDescription')}
      />
      <Inputs>
        <Input
          type="text"
          id="mailpoet_premium_key"
          name="premium[premium_key]"
          value={state.key || ''}
          onChange={(event) =>
            setState({
              mssStatus: null,
              premiumStatus: null,
              premiumInstallationStatus: null,
              key: event.target.value.trim() || null,
            })
          }
        />
        <Button type="button" onClick={verifyKey}>
          {t('premiumTabVerifyButton')}
        </Button>
        {state.isKeyValid !== null &&
          Messages(state, showPendingApprovalNotice, activationCallback)}
      </Inputs>
      {showFromAddressModal && (
        <SetFromAddressModal
          onRequestClose={() => {
            setState({ fromAddressModalCanBeShown: false });
            sendCongratulatoryMssEmail();
          }}
          setAuthorizedAddress={setAuthorizedAddress}
        />
      )}
    </div>
  );
}
