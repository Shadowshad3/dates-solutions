<?php

declare(strict_types=1);

namespace Drupal\dates_forms\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Consultation request form for the Book a Consultation page.
 */
class ConsultationRequestForm extends FormBase {

  /**
   * The mail manager.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected MailManagerInterface $mailManager;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $siteConfigFactory;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected LoggerChannelFactoryInterface $loggerChannelFactory;

  /**
   * Constructs the form.
   */
  public function __construct(MailManagerInterface $mail_manager, ConfigFactoryInterface $config_factory, LoggerChannelFactoryInterface $logger_factory) {
    $this->mailManager = $mail_manager;
    $this->siteConfigFactory = $config_factory;
    $this->loggerChannelFactory = $logger_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('plugin.manager.mail'),
      $container->get('config.factory'),
      $container->get('logger.factory'),
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

    $form['project_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Project type'),
      '#required' => TRUE,
      '#options' => [
        '' => $this->t('- Select project type -'),
        'new_website' => $this->t('New website'),
        'existing_website_improvement' => $this->t('Existing website improvement'),
        'saas_platform_build' => $this->t('SaaS / platform build'),
        'support_maintenance' => $this->t('Support & maintenance'),
        'integration_work' => $this->t('Integration work'),
        'network_lan_cabling_ph' => $this->t('Network / LAN cabling project (Philippines only)'),
        'consultation_only' => $this->t('Consultation only'),
      ],
    ];

    $form['website_url'] = [
      '#type' => 'url',
      '#title' => $this->t('Current website or platform URL'),
      '#maxlength' => 255,
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
      '#options' => [
        '' => $this->t('- Select timeline -'),
        'asap' => $this->t('As soon as possible'),
        'within_30_days' => $this->t('Within 30 days'),
        'within_90_days' => $this->t('Within 90 days'),
        'this_quarter' => $this->t('This quarter'),
        'exploring' => $this->t('Still exploring'),
      ],
    ];

    $form['timeline_budget']['budget_range'] = [
      '#type' => 'select',
      '#title' => $this->t('Budget range'),
      '#required' => TRUE,
      '#options' => [
        '' => $this->t('- Select budget range -'),
        'not_sure' => $this->t('Not sure yet'),
        'under_100k_php' => $this->t('Under ₱100k'),
        '100k_to_300k_php' => $this->t('₱100k–₱300k'),
        '300k_to_1m_php' => $this->t('₱300k–₱1M'),
        'over_1m_php' => $this->t('₱1M+'),
        'prefer_to_discuss' => $this->t('Prefer to discuss'),
      ],
    ];

    $form['preferred_contact_method'] = [
      '#type' => 'radios',
      '#title' => $this->t('Preferred contact method'),
      '#required' => TRUE,
      '#options' => [
        'email' => $this->t('Email'),
        'phone' => $this->t('Phone'),
        'either' => $this->t('Either is fine'),
      ],
      '#default_value' => 'email',
    ];

    $form['consent'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('I agree to be contacted regarding this consultation request.'),
      '#required' => TRUE,
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
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $values = $form_state->getValues();
    $recipient = (string) $this->siteConfigFactory->get('system.site')->get('mail');

    if ($recipient === '') {
      $this->messenger()->addError($this->t('Site email is not configured yet. Please set the site email address first.'));
      return;
    }

    $subject = $this->t('[Date Solutions] Consultation request from @name', [
      '@name' => $values['full_name'],
    ])->render();

    $lines = [
      'Date Solutions consultation request',
      '===================================',
      'Full name: ' . $values['full_name'],
      'Work email: ' . $values['work_email'],
      'Company / organization: ' . ($values['company'] ?: '-'),
      'Country: ' . ($values['country'] ?: '-'),
      'Project type: ' . ($values['project_type'] ?: '-'),
      'Current website / platform URL: ' . ($values['website_url'] ?: '-'),
      'Target timeline: ' . ($values['target_timeline'] ?: '-'),
      'Budget range: ' . ($values['budget_range'] ?: '-'),
      'Preferred contact method: ' . ($values['preferred_contact_method'] ?: '-'),
      '',
      'Project details:',
      trim((string) $values['help_needed']),
    ];

    $result = $this->mailManager->mail(
      'dates_forms',
      'lead_notification',
      $recipient,
      $this->currentUser()->getPreferredLangcode(),
      [
        'subject' => $subject,
        'lines' => $lines,
      ],
      $values['work_email'],
      TRUE,
    );

    if (!empty($result['result'])) {
      $this->messenger()->addStatus($this->t('Thank you. Your consultation request has been sent. We will review it and get back to you soon.'));
      $form_state->setRebuild(FALSE);
      return;
    }

    $this->loggerChannelFactory->get('dates_forms')->error('Consultation request email failed for %email.', ['%email' => $values['work_email']]);
    $this->messenger()->addError($this->t('Sorry, we could not send your request right now. Please try again later.'));
  }

}
