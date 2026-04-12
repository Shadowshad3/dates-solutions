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
 * Consultation request form for the Book a Consultation page.
 */
class ConsultationRequestForm extends FormBase {

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
    return 'dates_forms_consultation_request_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['#attributes']['class'][] = 'dates-form';
    $form['#attributes']['class'][] = 'dates-form--consultation';

    $form['intro'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['dates-form__intro'],
      ],
    ];
    $form['intro']['title'] = [
      '#markup' => '<h3 class="dates-form__title">Request a consultation</h3>',
    ];
    $form['intro']['copy'] = [
      '#markup' => '<p class="dates-form__copy">Share your current situation and we will review the most practical next step for your website, platform, support need, or technical direction.</p>',
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

    $form['top']['work_email'] = [
      '#type' => 'email',
      '#title' => $this->t('Work email'),
      '#required' => TRUE,
      '#maxlength' => 190,
    ];

    $form['top']['company'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Company or organization'),
      '#maxlength' => 160,
    ];

    $form['top']['country'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Country'),
      '#maxlength' => 120,
    ];

    $form['project_meta'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['dates-form__grid', 'dates-form__grid--two'],
      ],
    ];

    $form['project_meta']['project_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Project type'),
      '#required' => TRUE,
      '#options' => $this->getProjectTypeOptions(),
    ];

    $form['project_meta']['website_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Current website or platform URL'),
      '#maxlength' => 255,
      '#description' => $this->t('Optional. Example: https://example.com or www.example.com'),
    ];

    $form['help_needed'] = [
      '#type' => 'textarea',
      '#title' => $this->t('What do you need help with?'),
      '#required' => TRUE,
      '#rows' => 7,
      '#description' => $this->t('Briefly describe the project, current challenge, or decision you need help with.'),
    ];

    $form['timeline_budget'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['dates-form__grid', 'dates-form__grid--two'],
      ],
    ];

    $form['timeline_budget']['target_timeline'] = [
      '#type' => 'select',
      '#title' => $this->t('Target timeline'),
      '#required' => TRUE,
      '#options' => $this->getTimelineOptions(),
    ];

    $form['timeline_budget']['budget_range'] = [
      '#type' => 'select',
      '#title' => $this->t('Budget range'),
      '#required' => TRUE,
      '#options' => $this->getBudgetOptions(),
    ];

    $form['preferred_contact_method'] = [
      '#type' => 'radios',
      '#title' => $this->t('Preferred contact method'),
      '#required' => TRUE,
      '#options' => $this->getPreferredContactOptions(),
      '#default_value' => 'email',
    ];

    $form['consent'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('I agree to be contacted regarding this consultation request.'),
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
      '#value' => $this->t('Request consultation'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $details = trim((string) $form_state->getValue('help_needed'));
    if (mb_strlen($details) < 30) {
      $form_state->setErrorByName('help_needed', $this->t('Please provide a little more project detail so we can understand your consultation request.'));
    }

    $this->validateSpamProtection($form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $values = $form_state->getValues();
    $site_mail = (string) $this->siteConfigFactory->get('system.site')->get('mail');
    $submitter_email = (string) ($values['work_email'] ?? '');

    if ($site_mail === '') {
      $this->messenger()->addError($this->t('Site email is not configured yet. Please set the site email address first.'));
      return;
    }

    $this->registerFloodEvent();

    $subject = $this->t('[Date Solutions] Consultation request from @name', [
      '@name' => $values['full_name'],
    ])->render();

    $lines = [
      'Date Solutions consultation request',
      '===================================',
      'Full name: ' . $this->cleanLine($values['full_name'] ?? ''),
      'Work email: ' . $this->cleanLine($submitter_email),
      'Company / organization: ' . $this->cleanLine($values['company'] ?: '-'),
      'Country: ' . $this->cleanLine($values['country'] ?: '-'),
      'Project type: ' . $this->getLabel($this->getProjectTypeOptions(), (string) ($values['project_type'] ?? '')),
      'Current website / platform URL: ' . $this->cleanLine($values['website_url'] ?: '-'),
      'Target timeline: ' . $this->getLabel($this->getTimelineOptions(), (string) ($values['target_timeline'] ?? '')),
      'Budget range: ' . $this->getLabel($this->getBudgetOptions(), (string) ($values['budget_range'] ?? '')),
      'Preferred contact method: ' . $this->getLabel($this->getPreferredContactOptions(), (string) ($values['preferred_contact_method'] ?? '')),
      '',
      'Project details:',
      $this->cleanMultiline($values['help_needed'] ?? ''),
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
      $this->messenger()->addStatus($this->t('Thank you. Your consultation request has been sent. We will review it and get back to you soon.'));
      $form_state->setRedirect('<current>');
      return;
    }

    $this->loggerChannelFactory->get('dates_forms')->error('Consultation request email failed for %email.', ['%email' => $submitter_email]);
    $this->messenger()->addError($this->t('Sorry, we could not send your request right now. Please try again later.'));
  }

  /**
   * Returns the project type options.
   */
  protected function getProjectTypeOptions(): array {
    return [
      '' => $this->t('- Select project type -'),
      'new_website' => $this->t('New website'),
      'existing_website_improvement' => $this->t('Existing website improvement'),
      'saas_platform_build' => $this->t('SaaS / platform build'),
      'support_maintenance' => $this->t('Support & maintenance'),
      'integration_work' => $this->t('Integration work'),
      'network_lan_cabling_ph' => $this->t('Network / LAN cabling project (Philippines only)'),
      'consultation_only' => $this->t('Consultation only'),
    ];
  }

  /**
   * Returns the timeline options.
   */
  protected function getTimelineOptions(): array {
    return [
      '' => $this->t('- Select timeline -'),
      'asap' => $this->t('As soon as possible'),
      'within_30_days' => $this->t('Within 30 days'),
      'within_90_days' => $this->t('Within 90 days'),
      'this_quarter' => $this->t('This quarter'),
      'exploring' => $this->t('Still exploring'),
    ];
  }

  /**
   * Returns the budget options.
   */
  protected function getBudgetOptions(): array {
    return [
      '' => $this->t('- Select budget range -'),
      'not_sure' => $this->t('Not sure yet'),
      'under_100k_php' => $this->t('Under ₱100k'),
      '100k_to_300k_php' => $this->t('₱100k–₱300k'),
      '300k_to_1m_php' => $this->t('₱300k–₱1M'),
      'over_1m_php' => $this->t('₱1M+'),
      'prefer_to_discuss' => $this->t('Prefer to discuss'),
    ];
  }

  /**
   * Returns the preferred contact options.
   */
  protected function getPreferredContactOptions(): array {
    return [
      'email' => $this->t('Email'),
      'phone' => $this->t('Phone'),
      'either' => $this->t('Either is fine'),
    ];
  }

  /**
   * Sends the autoresponse email.
   */
  protected function sendAutoResponse(string $email, string $name, string $site_mail): void {
    if ($email === '') {
      return;
    }

    $subject = $this->t('[Date Solutions] We received your consultation request')->render();
    $display_name = $name !== '' ? $name : 'there';

    $lines = [
      'Hi ' . $display_name . ',',
      '',
      'Thank you for reaching out to Date Solutions.',
      'We received your consultation request and our team will review it shortly.',
      '',
      'What happens next:',
      '- We review your request and project context.',
      '- We may follow up if we need clarification.',
      '- We reply with the most practical next step for your situation.',
      '',
      'If your request is urgent, you may reply directly to this email.',
      '',
      'Date Solutions',
      'Drupal, SaaS, and long-term web support',
    ];

    $result = $this->mailManager->mail(
      'dates_forms',
      'consultation_autoresponse',
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
      $this->loggerChannelFactory->get('dates_forms')->warning('Consultation autoresponse email failed for %email.', ['%email' => $email]);
    }
  }

  /**
   * Validates the anti-spam rules.
   */
  protected function validateSpamProtection(FormStateInterface $form_state): void {
    if (trim((string) $form_state->getValue('fax_number')) !== '') {
      $this->loggerChannelFactory->get('dates_forms')->warning('Consultation honeypot triggered for IP %ip.', ['%ip' => $this->getClientIp()]);
      $form_state->setErrorByName('full_name', $this->t('We could not process your submission. Please try again.'));
      return;
    }

    $loaded_at = (int) $form_state->getValue('form_loaded_at');
    if ($loaded_at <= 0 || ($this->time->getRequestTime() - $loaded_at) < 4) {
      $this->loggerChannelFactory->get('dates_forms')->warning('Consultation submitted too quickly from IP %ip.', ['%ip' => $this->getClientIp()]);
      $form_state->setErrorByName('full_name', $this->t('Please review your details and submit the form again.'));
      return;
    }

    if (!$this->flood->isAllowed($this->getFloodEventName(), 5, 3600, $this->getClientIp())) {
      $this->loggerChannelFactory->get('dates_forms')->warning('Consultation flood limit reached for IP %ip.', ['%ip' => $this->getClientIp()]);
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
    return 'dates_forms.consultation_request';
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