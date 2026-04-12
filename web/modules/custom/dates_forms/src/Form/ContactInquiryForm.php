<?php

declare(strict_types=1);

namespace Drupal\dates_forms\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Flood\FloodInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Component\Utility\Unicode;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Xss;
use Drupal\Component\Datetime\TimeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Contact inquiry form for the Contact Us page.
 */
class ContactInquiryForm extends FormBase {

  /**
   * The mail manager.
   */
  protected MailManagerInterface $mailManager;

  /**
   * The config factory.
   */
  protected ConfigFactoryInterface $siteConfigFactory;

  /**
   * The logger factory.
   */
  protected LoggerChannelFactoryInterface $loggerChannelFactory;

  /**
   * The flood service.
   */
  protected FloodInterface $flood;

  /**
   * The time service.
   */
  protected TimeInterface $time;

  /**
   * The request stack.
   */
  protected RequestStack $requestStack;

  /**
   * Constructs the form.
   */
  public function __construct(
    MailManagerInterface $mail_manager,
    ConfigFactoryInterface $config_factory,
    LoggerChannelFactoryInterface $logger_factory,
    FloodInterface $flood,
    TimeInterface $time,
    RequestStack $request_stack,
  ) {
    $this->mailManager = $mail_manager;
    $this->siteConfigFactory = $config_factory;
    $this->loggerChannelFactory = $logger_factory;
    $this->flood = $flood;
    $this->time = $time;
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('plugin.manager.mail'),
      $container->get('config.factory'),
      $container->get('logger.factory'),
      $container->get('flood'),
      $container->get('datetime.time'),
      $container->get('request_stack'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'dates_forms_contact_inquiry_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['#attributes']['class'][] = 'dates-form';
    $form['#attributes']['class'][] = 'dates-form--contact';

    $form['intro'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['dates-form__intro'],
      ],
    ];
    $form['intro']['title'] = [
      '#markup' => '<h3 class="dates-form__title">Send us a message</h3>',
    ];
    $form['intro']['copy'] = [
      '#markup' => '<p class="dates-form__copy">Tell us about your website, platform, support need, or digital project and we will review the best next step with you.</p>',
    ];

    $form['top'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['dates-form__grid', 'dates-form__grid--two'],
      ],
    ];

    $form['top']['full_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Full name'),
      '#required' => TRUE,
      '#maxlength' => 120,
    ];

    $form['top']['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email address'),
      '#required' => TRUE,
      '#maxlength' => 190,
    ];

    $form['top']['company'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Company or organization'),
      '#maxlength' => 160,
    ];

    $form['top']['phone'] = [
      '#type' => 'tel',
      '#title' => $this->t('Phone number'),
      '#maxlength' => 40,
    ];

    $form['service_interest'] = [
      '#type' => 'select',
      '#title' => $this->t('Service interest'),
      '#required' => TRUE,
      '#options' => $this->getServiceInterestOptions(),
    ];

    $form['message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Message'),
      '#required' => TRUE,
      '#rows' => 6,
      '#description' => $this->t('Briefly describe your requirement, current issue, or project direction.'),
    ];

    $form['consent'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('I agree to be contacted regarding this inquiry.'),
      '#required' => TRUE,
    ];

    $form['fax_number'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Fax number'),
      '#default_value' => '',
      '#attributes' => [
        'autocomplete' => 'off',
        'tabindex' => '-1',
        'aria-hidden' => 'true',
      ],
      '#wrapper_attributes' => [
        'style' => 'position:absolute;left:-10000px;top:auto;width:1px;height:1px;overflow:hidden;',
        'aria-hidden' => 'true',
      ],
    ];

    $form['form_loaded_at'] = [
      '#type' => 'hidden',
      '#value' => (string) $this->time->getRequestTime(),
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Send inquiry'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $message = trim((string) $form_state->getValue('message'));
    if (mb_strlen($message) < 20) {
      $form_state->setErrorByName('message', $this->t('Please provide a little more detail so we can understand your request.'));
    }

    $this->validateSpamProtection($form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $values = $form_state->getValues();
    $site_mail = (string) $this->siteConfigFactory->get('system.site')->get('mail');
    $submitter_email = (string) ($values['email'] ?? '');

    if ($site_mail === '') {
      $this->messenger()->addError($this->t('Site email is not configured yet. Please set the site email address first.'));
      return;
    }

    $this->registerFloodEvent();

    $subject = $this->t('[Date Solutions] Contact inquiry from @name', [
      '@name' => $values['full_name'],
    ])->render();

    $lines = [
      'Date Solutions contact inquiry',
      '================================',
      'Full name: ' . $this->cleanLine($values['full_name'] ?? ''),
      'Email: ' . $this->cleanLine($submitter_email),
      'Company / organization: ' . $this->cleanLine($values['company'] ?: '-'),
      'Phone: ' . $this->cleanLine($values['phone'] ?: '-'),
      'Service interest: ' . $this->getLabel($this->getServiceInterestOptions(), (string) ($values['service_interest'] ?? '')),
      '',
      'Message:',
      $this->cleanMultiline($values['message'] ?? ''),
    ];

    $result = $this->mailManager->mail(
      'dates_forms',
      'lead_notification',
      $site_mail,
      $this->currentUser()->getPreferredLangcode(),
      [
        'subject' => $subject,
        'lines' => $lines,
        'from' => $site_mail,
        'reply_to' => $submitter_email,
      ],
      $site_mail,
      TRUE,
    );

    if (!empty($result['result'])) {
      $this->sendAutoResponse($submitter_email, (string) ($values['full_name'] ?? ''), $site_mail);
      $this->messenger()->addStatus($this->t('Thank you. Your message has been sent. We will get back to you soon.'));
      $form_state->setRedirect('<current>');
      return;
    }

    $this->loggerChannelFactory->get('dates_forms')->error('Contact inquiry email failed for %email.', ['%email' => $submitter_email]);
    $this->messenger()->addError($this->t('Sorry, we could not send your message right now. Please try again later.'));
  }

  /**
   * Returns the service interest options.
   */
  protected function getServiceInterestOptions(): array {
    return [
      '' => $this->t('- Select a service -'),
      'drupal_website_development' => $this->t('Drupal Website Development'),
      'saas_product_development' => $this->t('SaaS Product Development'),
      'website_support_maintenance' => $this->t('Website Support & Maintenance'),
      'website_optimization' => $this->t('Website Optimization'),
      'system_integrations' => $this->t('System Integrations'),
      'network_lan_cabling_ph' => $this->t('Large-Scale Network & LAN Cabling (Philippines only)'),
      'other' => $this->t('Other'),
    ];
  }

  /**
   * Sends the autoresponse email.
   */
  protected function sendAutoResponse(string $email, string $name, string $site_mail): void {
    if ($email === '') {
      return;
    }

    $subject = $this->t('[Date Solutions] We received your inquiry')->render();
    $display_name = $name !== '' ? $name : 'there';

    $lines = [
      'Hi ' . $display_name . ',',
      '',
      'Thank you for contacting Date Solutions.',
      'We received your inquiry and our team will review it as soon as possible.',
      '',
      'What happens next:',
      '- We review the details you submitted.',
      '- We may reply by email if we need clarification.',
      '- We send back the most practical next step based on your request.',
      '',
      'If your concern is urgent, you may reply directly to this email.',
      '',
      'Date Solutions',
      'Drupal, SaaS, and long-term web support',
    ];

    $result = $this->mailManager->mail(
      'dates_forms',
      'contact_autoresponse',
      $email,
      $this->currentUser()->getPreferredLangcode(),
      [
        'subject' => $subject,
        'lines' => $lines,
        'from' => $site_mail,
        'reply_to' => $site_mail,
      ],
      $site_mail,
      TRUE,
    );

    if (empty($result['result'])) {
      $this->loggerChannelFactory->get('dates_forms')->warning('Contact inquiry autoresponse email failed for %email.', ['%email' => $email]);
    }
  }

  /**
   * Validates the anti-spam rules.
   */
  protected function validateSpamProtection(FormStateInterface $form_state): void {
    if (trim((string) $form_state->getValue('fax_number')) !== '') {
      $this->loggerChannelFactory->get('dates_forms')->warning('Contact inquiry honeypot triggered for IP %ip.', ['%ip' => $this->getClientIp()]);
      $form_state->setErrorByName('full_name', $this->t('We could not process your submission. Please try again.'));
      return;
    }

    $loaded_at = (int) $form_state->getValue('form_loaded_at');
    if ($loaded_at <= 0 || ($this->time->getRequestTime() - $loaded_at) < 4) {
      $this->loggerChannelFactory->get('dates_forms')->warning('Contact inquiry submitted too quickly from IP %ip.', ['%ip' => $this->getClientIp()]);
      $form_state->setErrorByName('full_name', $this->t('Please review your details and submit the form again.'));
      return;
    }

    if (!$this->flood->isAllowed($this->getFloodEventName(), 5, 3600, $this->getClientIp())) {
      $this->loggerChannelFactory->get('dates_forms')->warning('Contact inquiry flood limit reached for IP %ip.', ['%ip' => $this->getClientIp()]);
      $form_state->setErrorByName('full_name', $this->t('Too many submissions were received from your connection. Please try again later.'));
    }
  }

  /**
   * Registers the submission in flood control.
   */
  protected function registerFloodEvent(): void {
    $this->flood->register($this->getFloodEventName(), 3600, $this->getClientIp());
  }

  /**
   * Returns the flood event name.
   */
  protected function getFloodEventName(): string {
    return 'dates_forms.contact_inquiry';
  }

  /**
   * Returns the current client IP.
   */
  protected function getClientIp(): string {
    return $this->requestStack->getCurrentRequest()?->getClientIp() ?: 'unknown';
  }

  /**
   * Returns the human-readable label for an option value.
   */
  protected function getLabel(array $options, string $value): string {
    if ($value === '' || !isset($options[$value])) {
      return '-';
    }

    return (string) $options[$value];
  }

  /**
   * Cleans a single-line value for email output.
   */
  protected function cleanLine(mixed $value): string {
    $string = trim(Xss::filter((string) $value));
    return $string !== '' ? Unicode::truncate($string, 300, TRUE, TRUE) : '-';
  }

  /**
   * Cleans a multi-line value for email output.
   */
  protected function cleanMultiline(mixed $value): string {
    $string = trim(Xss::filter((string) $value));
    return $string !== '' ? Html::decodeEntities($string) : '-';
  }

}