<?php

namespace MailPoet\Test\Subscription;

use Codeception\Stub;
use MailPoet\API\JSON\API;
use MailPoet\API\JSON\ErrorResponse;
use MailPoet\API\JSON\Response;
use MailPoet\DI\ContainerWrapper;
use MailPoet\Entities\FormEntity;
use MailPoet\Form\Util\FieldNameObfuscator;
use MailPoet\Models\Segment as SegmentModel;
use MailPoet\Models\Subscriber as SubscriberModel;
use MailPoet\Settings\SettingsController;
use MailPoet\Settings\SettingsRepository;
use MailPoet\Subscription\Form;
use MailPoet\Util\Url as UrlHelper;
use MailPoet\WP\Functions as WPFunctions;
use MailPoetVendor\Idiorm\ORM;

class FormTest extends \MailPoetTest {
  public $post;
  public $requestData;
  public $form;
  public $segment;
  public $testEmail;

  /** @var SettingsController */
  private $settings;

  public function _before() {
    parent::_before();
    $this->settings = SettingsController::getInstance();
    $this->settings->set('sender', [
      'name' => 'John Doe',
      'address' => 'john.doe@example.com',
    ]);
    $this->testEmail = 'test@example.com';
    $this->segment = SegmentModel::createOrUpdate(
      [
        'name' => 'Test segment',
      ]
    );
    $this->form = new FormEntity('Test form');
    $this->form->setBody([
      [
        'type' => 'text',
        'id' => 'email',
      ],
    ]);
    $this->form->setSettings([
      'segments' => [$this->segment->id],
    ]);
    $this->entityManager->persist($this->form);
    $this->entityManager->flush();
    $obfuscator = new FieldNameObfuscator(WPFunctions::get());
    $obfuscatedEmail = $obfuscator->obfuscate('email');
    $this->requestData = [
      'action' => 'mailpoet_subscription_form',
      'data' => [
        'form_id' => $this->form->getId(),
        $obfuscatedEmail => $this->testEmail,
      ],
      'token' => WPFunctions::get()->wpCreateNonce('mailpoet_token'),
      'api_version' => 'v1',
      'endpoint' => 'subscribers',
      'mailpoet_method' => 'subscribe',
    ];
    $this->post = wp_insert_post(
      [
        'post_title' => 'Sample Post',
        'post_content' => 'contents',
        'post_status' => 'publish',
      ]
    );
    $this->settings->set('signup_confirmation.enabled', false);
  }

  public function testItSubscribesAndRedirectsBackWithSuccessResponse() {
    $urlHelper = Stub::make(UrlHelper::class, [
      'redirectBack' => function($params) {
        return $params;
      },
    ], $this);
    $formController = new Form(ContainerWrapper::getInstance()->get(API::class), $urlHelper);
    $result = $formController->onSubmit($this->requestData);
    expect(SubscriberModel::findOne($this->testEmail))->notEmpty();
    expect($result['mailpoet_success'])->equals($this->form->getId());
    expect($result['mailpoet_error'])->null();
  }

  public function testItSubscribesAndRedirectsToCustomUrlWithSuccessResponse() {
    // update form with a redirect setting
    $form = $this->form;
    $formSettings = $form->getSettings();
    $formSettings['on_success'] = 'page';
    $formSettings['success_page'] = $this->post;
    $form->setSettings($formSettings);
    $this->entityManager->flush();
    $urlHelper = Stub::make(UrlHelper::class, [
      'redirectTo' => function($params) {
        return $params;
      },
      'redirectBack' => function($params) {
        return $params;
      },
    ], $this);
    $formController = new Form(ContainerWrapper::getInstance()->get(API::class), $urlHelper);
    $result = $formController->onSubmit($this->requestData);
    expect(SubscriberModel::findOne($this->testEmail))->notEmpty();
    expect($result)->regExp('/http.*?sample-post|http.*?\?p=\d+/i');
  }

  public function testItDoesNotSubscribeAndRedirectsBackWithErrorResponse() {
    // clear subscriber email so that subscription fails
    $requestData = $this->requestData;
    $requestData['data']['email'] = false;
    $urlHelper = Stub::make(UrlHelper::class, [
      'redirectBack' => function($params) {
        return $params;
      },
    ], $this);
    $formController = new Form(ContainerWrapper::getInstance()->get(API::class), $urlHelper);
    $result = $formController->onSubmit($requestData);
    expect(SubscriberModel::findMany())->isEmpty();
    expect($result['mailpoet_error'])->equals($this->form->getId());
    expect($result['mailpoet_success'])->null();
  }

  public function testItDoesNotSubscribeAndRedirectsToRedirectUrlIfPresent() {
    $redirectUrl = 'http://test/';
    $urlHelper = Stub::make(UrlHelper::class, [
      'redirectTo' => function($params) {
        return $params;
      },
    ], $this);
    $api = Stub::makeEmpty(API::class, [
      'processRoute' => function() use ($redirectUrl) {
        return new ErrorResponse([], ['redirect_url' => $redirectUrl], Response::STATUS_BAD_REQUEST);
      },
    ], $this);
    $formController = new Form($api, $urlHelper);
    $result = $formController->onSubmit($this->requestData);
    expect($result)->equals($redirectUrl);
  }

  public function _after() {
    wp_delete_post($this->post);
    ORM::raw_execute('TRUNCATE ' . SegmentModel::$_table);
    $this->truncateEntity(FormEntity::class);
    ORM::raw_execute('TRUNCATE ' . SubscriberModel::$_table);
    $this->diContainer->get(SettingsRepository::class)->truncate();
  }
}
