<?php

namespace MailPoet\Subscription;

use MailPoet\Config\Renderer as TemplateRenderer;
use MailPoet\Entities\SegmentEntity;
use MailPoet\Entities\StatisticsUnsubscribeEntity;
use MailPoet\Entities\SubscriberEntity;
use MailPoet\Features\FeaturesController;
use MailPoet\Form\AssetsController;
use MailPoet\Newsletter\Scheduler\WelcomeScheduler;
use MailPoet\Settings\TrackingConfig;
use MailPoet\Statistics\Track\SubscriberHandler;
use MailPoet\Statistics\Track\Unsubscribes;
use MailPoet\Subscribers\LinkTokens;
use MailPoet\Subscribers\NewSubscriberNotificationMailer;
use MailPoet\Subscribers\SubscriberSaveController;
use MailPoet\Subscribers\SubscriberSegmentRepository;
use MailPoet\Subscribers\SubscribersRepository;
use MailPoet\Util\Helpers;
use MailPoet\WP\Functions as WPFunctions;
use MailPoetVendor\Carbon\Carbon;
use MailPoetVendor\Doctrine\ORM\EntityManager;

class Pages {
  const DEMO_EMAIL = 'demo@mailpoet.com';
  const ACTION_CAPTCHA = 'captcha';
  const ACTION_CONFIRM = 'confirm';
  const ACTION_CONFIRM_UNSUBSCRIBE = 'confirm_unsubscribe';
  const ACTION_MANAGE = 'manage';
  const ACTION_UNSUBSCRIBE = 'unsubscribe';
  const ACTION_RE_ENGAGEMENT = 're_engagement';

  private $action;
  private $data;
  private $subscriber;

  /** @var NewSubscriberNotificationMailer */
  private $newSubscriberNotificationSender;

  /** @var WPFunctions */
  private $wp;

  /** @var CaptchaRenderer */
  private $captchaRenderer;

  /** @var WelcomeScheduler */
  private $welcomeScheduler;

  /** @var LinkTokens */
  private $linkTokens;

  /** @var SubscriptionUrlFactory */
  private $subscriptionUrlFactory;

  /** @var AssetsController */
  private $assetsController;

  /** @var TemplateRenderer */
  private $templateRenderer;

  /** @var Unsubscribes */
  private $unsubscribesTracker;

  /** @var ManageSubscriptionFormRenderer */
  private $manageSubscriptionFormRenderer;

  /** @var SubscriberHandler */
  private $subscriberHandler;

  /** @var SubscribersRepository */
  private $subscribersRepository;

  /** @var TrackingConfig */
  private $trackingConfig;

  /** @var FeaturesController */
  private $featuresController;

  /** @var EntityManager */
  private $entityManager;

  /** @var SubscriberSaveController */
  private $subscriberSaveController;

  /** @var SubscriberSegmentRepository */
  private $subscriberSegmentRepository;

  public function __construct(
    NewSubscriberNotificationMailer $newSubscriberNotificationSender,
    WPFunctions $wp,
    CaptchaRenderer $captchaRenderer,
    WelcomeScheduler $welcomeScheduler,
    LinkTokens $linkTokens,
    SubscriptionUrlFactory $subscriptionUrlFactory,
    AssetsController $assetsController,
    TemplateRenderer $templateRenderer,
    Unsubscribes $unsubscribesTracker,
    ManageSubscriptionFormRenderer $manageSubscriptionFormRenderer,
    SubscriberHandler $subscriberHandler,
    SubscribersRepository $subscribersRepository,
    TrackingConfig $trackingConfig,
    FeaturesController $featuresController,
    EntityManager $entityManager,
    SubscriberSaveController $subscriberSaveController,
    SubscriberSegmentRepository $subscriberSegmentRepository
  ) {
    $this->wp = $wp;
    $this->newSubscriberNotificationSender = $newSubscriberNotificationSender;
    $this->captchaRenderer = $captchaRenderer;
    $this->welcomeScheduler = $welcomeScheduler;
    $this->linkTokens = $linkTokens;
    $this->subscriptionUrlFactory = $subscriptionUrlFactory;
    $this->assetsController = $assetsController;
    $this->templateRenderer = $templateRenderer;
    $this->unsubscribesTracker = $unsubscribesTracker;
    $this->manageSubscriptionFormRenderer = $manageSubscriptionFormRenderer;
    $this->subscriberHandler = $subscriberHandler;
    $this->subscribersRepository = $subscribersRepository;
    $this->trackingConfig = $trackingConfig;
    $this->featuresController = $featuresController;
    $this->entityManager = $entityManager;
    $this->subscriberSaveController = $subscriberSaveController;
    $this->subscriberSegmentRepository = $subscriberSegmentRepository;
  }

  public function init($action = false, $data = [], $initShortcodes = false, $initPageFilters = false) {
    $this->action = $action;
    $this->data = $data;
    $this->subscriber = $this->getSubscriber();
    if ($initPageFilters) $this->initPageFilters();
    if ($initShortcodes) $this->initShortcodes();
    return $this;
  }

  private function isPreview() {
    return (array_key_exists('preview', $_GET) || array_key_exists('preview', $this->data));
  }

  public function initPageFilters() {
    $this->wp->addFilter('wp_title', [$this,'setWindowTitle'], 10, 3);
    $this->wp->addFilter('document_title_parts', [$this,'setWindowTitleParts'], 10, 1);
    $this->wp->addFilter('the_title', [$this,'setPageTitle'], 10, 1);
    $this->wp->addFilter('the_content', [$this,'setPageContent'], 10, 1);
    $this->wp->removeAction('wp_head', 'noindex', 1);
    $this->wp->addAction('wp_head', [$this, 'setMetaRobots'], 1);
  }

  public function initShortcodes() {
    $this->wp->addShortcode('mailpoet_manage', [$this, 'getManageLink']);
    $this->wp->addShortcode('mailpoet_manage_subscription', [$this, 'getManageContent']);
  }

  /**
   * @return SubscriberEntity|null
   */
  private function getSubscriber() {
    if (!is_null($this->subscriber)) {
      return $this->subscriber;
    }

    $token = (isset($this->data['token'])) ? $this->data['token'] : null;
    $email = (isset($this->data['email'])) ? $this->data['email'] : null;
    $wpUser = $this->wp->wpGetCurrentUser();

    if (!$email && $wpUser->exists()) {
      $subscriber = $this->subscribersRepository->findOneBy(['wpUserId' => $wpUser->ID]);
      return $subscriber instanceof SubscriberEntity ? $subscriber : null;
    }

    if (!$email) {
      return null;
    }

    $subscriber = $this->subscribersRepository->findOneBy(['email' => $email]);
    return ($subscriber instanceof SubscriberEntity && $this->linkTokens->verifyToken($subscriber, $token)) ? $subscriber : null;
  }

  public function confirm() {
    $this->subscriber = $this->getSubscriber();
    if ($this->subscriber === null) {
      return false;
    }

    $subscriberData = json_decode((string)$this->subscriber->getUnconfirmedData(), true);
    $originalStatus = $this->subscriber->getStatus();

    $this->subscriber->setStatus(SubscriberEntity::STATUS_SUBSCRIBED);
    $this->subscriber->setConfirmedIp(Helpers::getIP());
    $this->subscriber->setConfirmedAt(Carbon::createFromTimestamp($this->wp->currentTime('timestamp')));
    $this->subscriber->setLastSubscribedAt(Carbon::createFromTimestamp($this->wp->currentTime('timestamp')));
    $this->subscriber->setUnconfirmedData(null);

    try {
      $this->entityManager->persist($this->subscriber);
      $this->entityManager->flush();

      // start subscriber tracking
      $this->subscriberHandler->identifyByEmail($this->subscriber->getEmail());
    } catch (\Exception $e) {
      return false;
    }

    // Schedule welcome emails
    $subscriberSegments = $this->subscriber->getSegments()->toArray();
    if ($subscriberSegments) {
      $this->welcomeScheduler->scheduleSubscriberWelcomeNotification(
        $this->subscriber->getId(),
        array_map(function (SegmentEntity $segment) {
          return $segment->getId();
        }, $subscriberSegments)
      );
    }

    // when global status changes to subscribed, fire subscribed hook for all subscribed segments
    if ($this->featuresController->isSupported(FeaturesController::AUTOMATION)) {
      $segments = $this->subscriber->getSubscriberSegments();
      foreach ($segments as $subscriberSegment) {
        if ($subscriberSegment->getStatus() === SubscriberEntity::STATUS_SUBSCRIBED) {
          $this->wp->doAction('mailpoet_segment_subscribed', $subscriberSegment);
        }
      }
    }

    // Send new subscriber notification only when status changes to subscribed or there are unconfirmed data to avoid spamming
    if ($originalStatus !== SubscriberEntity::STATUS_SUBSCRIBED || $subscriberData !== null) {
      $this->newSubscriberNotificationSender->sendWithSubscriberAndSegmentEntities($this->subscriber, $subscriberSegments);
    }

    // Update subscriber from stored data after confirmation
    if (!empty($subscriberData)) {
      $this->subscriberSaveController->createOrUpdate((array)$subscriberData, $this->subscriber);
    }
  }

  public function unsubscribe() {
    if (
      !$this->isPreview()
      && ($this->subscriber !== null)
      && ($this->subscriber->status !== SubscriberEntity::STATUS_UNSUBSCRIBED)
    ) {
      if ($this->trackingConfig->isEmailTrackingEnabled() && isset($this->data['queueId'])) {
        $this->unsubscribesTracker->track(
          (int)$this->subscriber->id,
          StatisticsUnsubscribeEntity::SOURCE_NEWSLETTER,
          (int)$this->data['queueId']
        );
      }
      $this->subscriber->setStatus(SubscriberEntity::STATUS_UNSUBSCRIBED);
      $this->subscribersRepository->persist($this->subscriber);
      $this->subscribersRepository->flush();

      $this->subscriberSegmentRepository->unsubscribeFromSegments($this->subscriber);
    }
  }

  public function setMetaRobots() {
    echo '<meta name="robots" content="noindex,nofollow">';
  }

  public function setPageTitle($pageTitle = '') {
    global $post;

    if (
      ($post->post_title !== $this->wp->__('MailPoet Page', 'mailpoet')) // phpcs:ignore Squiz.NamingConventions.ValidVariableName.MemberNotCamelCaps
      ||
      ($pageTitle !== $this->wp->singlePostTitle('', false))
    ) {
      // when it's a custom page, just return the original page title
      return $pageTitle;
    } elseif ($this->action !== self::ACTION_CAPTCHA && $this->isPreview() === false && $this->subscriber === null) {
      return $this->wp->__("Hmmm... we don't have a record of you.", 'mailpoet');
    } else {
      // when it's our own page, generate page title based on requested action
      switch ($this->action) {
        case self::ACTION_CAPTCHA:
          return $this->captchaRenderer->getCaptchaPageTitle();

        case self::ACTION_CONFIRM:
          return $this->getConfirmTitle();

        case self::ACTION_CONFIRM_UNSUBSCRIBE:
          return $this->getConfirmUnsubscribeTitle();

        case self::ACTION_MANAGE:
          return $this->getManageTitle();

        case self::ACTION_UNSUBSCRIBE:
          return $this->getUnsubscribeTitle();

        case self::ACTION_RE_ENGAGEMENT:
          return $this->getReEngagementTitle();
      }
    }
  }

  public function setPageContent($pageContent = '[mailpoet_page]') {
    // if we're not in preview mode or captcha page and the subscriber does not exist
    if ($this->action !== self::ACTION_CAPTCHA && $this->isPreview() === false && $this->subscriber === null) {
      return $this->wp->__("Your email address doesn't appear in our lists anymore. Sign up again or contact us if this appears to be a mistake.", 'mailpoet');
    }

    $this->assetsController->setupFrontEndDependencies();

    if (strpos($pageContent, '[mailpoet_page]') !== false) {
      $content = '';

      switch ($this->action) {
        case self::ACTION_CAPTCHA:

          $captchaSessionId = isset($this->data['captcha_session_id']) ? $this->data['captcha_session_id'] : null;
          $content = $this->captchaRenderer->getCaptchaPageContent($captchaSessionId);
          break;
        case self::ACTION_CONFIRM:
          $content = $this->getConfirmContent();
          break;
        case self::ACTION_CONFIRM_UNSUBSCRIBE:
          $content = $this->getConfirmUnsubscribeContent();
          break;
        case self::ACTION_MANAGE:
          $content = $this->getManageContent();
          break;
        case self::ACTION_UNSUBSCRIBE:
          $content = $this->getUnsubscribeContent();
          break;
        case self::ACTION_RE_ENGAGEMENT:
          $content = $this->getReEngagementContent();
          break;
      }
      return str_replace('[mailpoet_page]', trim($content), $pageContent);
    } else {
      return $pageContent;
    }
  }

  public function setWindowTitle($title, $separator, $separatorLocation = 'right') {
    $titleParts = explode(" $separator ", $title);
    if (!is_array($titleParts)) {
      return $title;
    }
    if ($separatorLocation === 'right') {
      // first part
      $titleParts[0] = $this->setPageTitle($titleParts[0]);
    } else {
      // last part
      $lastIndex = count($titleParts) - 1;
      $titleParts[$lastIndex] = $this->setPageTitle($titleParts[$lastIndex]);
    }
    return implode(" $separator ", $titleParts);
  }

  public function setWindowTitleParts($meta = []) {
    $meta['title'] = $this->setPageTitle($meta['title']);
    return $meta;
  }

  private function getConfirmTitle() {
    if ($this->isPreview()) {
      $title = sprintf(
        $this->wp->__("You have subscribed to: %s", 'mailpoet'),
        'demo 1, demo 2'
      );
    } else {
      $segmentNames = array_map(function($segment) {
        return $segment->getName();
      }, $this->subscriber->getSegments()->toArray());

      if (empty($segmentNames)) {
        $title = $this->wp->__("You are now subscribed!", 'mailpoet');
      } else {
        $title = sprintf(
          $this->wp->__("You have subscribed to: %s", 'mailpoet'),
          join(', ', $segmentNames)
        );
      }
    }
    return $title;
  }

  private function getManageTitle() {
    if ($this->isPreview() || $this->subscriber !== null) {
      return $this->wp->__("Manage your subscription", 'mailpoet');
    }
  }

  private function getUnsubscribeTitle() {
    if ($this->isPreview() || $this->subscriber !== null) {
      return $this->wp->__("You are now unsubscribed.", 'mailpoet');
    }
  }

  private function getReEngagementTitle() {
    if ($this->isPreview() || $this->subscriber !== null) {
      return __('Thank you for letting us know!', 'mailpoet');
    }
  }

  private function getConfirmUnsubscribeTitle() {
    if ($this->isPreview() || $this->subscriber !== null) {
      return $this->wp->__('Confirm you want to unsubscribe', 'mailpoet');
    }
  }

  private function getConfirmContent() {
    if ($this->isPreview() || $this->subscriber !== null) {
      return $this->wp->__("Yup, we've added you to our email list. You'll hear from us shortly.", 'mailpoet');
    }
  }

  public function getManageContent() {
    if ($this->isPreview()) {
      $subscriber = new SubscriberEntity();
      $subscriber->setEmail(self::DEMO_EMAIL);
      $subscriber->setFirstName('John');
      $subscriber->setLastName('Doe');
      $subscriber->setLinkToken('bfd0889dbc7f081e171fa0cee7401df2');
    } else if ($this->subscriber !== null) {
      $subscriber = $this->subscriber;
    } else {
      return $this->wp->__('Subscription management form is only available to mailing lists subscribers.', 'mailpoet');
    }

    $formStatus = isset($_GET['success']) && absint(wp_unslash($_GET['success']))
      ? ManageSubscriptionFormRenderer::FORM_STATE_SUCCESS
      : ManageSubscriptionFormRenderer::FORM_STATE_NOT_SUBMITTED;

    return $this->wp->applyFilters(
      'mailpoet_manage_subscription_page',
      $this->manageSubscriptionFormRenderer->renderForm($subscriber, $formStatus)
    );
  }

  private function getUnsubscribeContent() {
    $content = '';
    if ($this->isPreview() || $this->subscriber !== null) {
      $content .= '<p>' . __('Accidentally unsubscribed?', 'mailpoet') . ' <strong>';
      $content .= '[mailpoet_manage]';
      $content .= '</strong></p>';
    }
    return $content;
  }

  private function getReEngagementContent() {
    $content = '';
    if ($this->isPreview() || $this->subscriber !== null) {
      $content .= '<p>' . __('We appreciate your continued interest in our updates. Expect to hear from us again soon!', 'mailpoet') . '</p>';
    }
    return $content;
  }

  private function getConfirmUnsubscribeContent() {
    if (!$this->isPreview() && $this->subscriber === null) {
      return '';
    }
    $queueId = isset($this->data['queueId']) ? (int)$this->data['queueId'] : null;
    $unsubscribeUrl = $this->subscriptionUrlFactory->getUnsubscribeUrl($this->subscriber, $queueId);
    $templateData = [
      'unsubscribeUrl' => $unsubscribeUrl,
    ];
    return $this->wp->applyFilters(
      'mailpoet_unsubscribe_confirmation_page',
      $this->templateRenderer->render('subscription/confirm_unsubscribe.html', $templateData),
      $unsubscribeUrl
    );
  }

  public function getManageLink($params) {
    if (!$this->subscriber instanceof SubscriberEntity) return __('Link to subscription management page is only available to mailing lists subscribers.', 'mailpoet');

    // get label or display default label
    $text = (
      isset($params['text'])
      ? htmlspecialchars($params['text'])
      : $this->wp->__('Manage your subscription', 'mailpoet')
    );

    return '<a href="' . $this->subscriptionUrlFactory->getManageUrl($this->subscriber) . '">' . $text . '</a>';
  }
}
