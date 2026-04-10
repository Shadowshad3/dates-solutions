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
 * Contact inquiry form for the Contact Us page.
 */
class ContactInquiryForm extends FormBase {

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
      '#options' => [
        '' => $this->t('- Select a service -'),
        'drupal_website_development' => $this->t('Drupal Website Development'),
        'saas_product_development' => $this->t('SaaS Product Development'),
        'website_support_maintenance' => $this->t('Website Support & Maintenance'),
        'website_optimization' => $this->t('Website Optimization'),
        'system_integrations' => $this->t('System Integrations'),
        'network_lan_cabling_ph' => $this->t('Large-Scale Network & LAN Cabling (Philippines only)'),
        'other' => $this->t('Other'),
      ],
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

    $subject = $this->t('[Date Solutions] Contact inquiry from @name', [
      '@name' => $values['full_name'],
    ])->render();

    $lines = [
      'Date Solutions contact inquiry',
      '================================',
      'Full name: ' . $values['full_name'],
      'Email: ' . $values['email'],
      'Company / organization: ' . ($values['company'] ?: '-'),
      'Phone: ' . ($values['phone'] ?: '-'),
      'Service interest: ' . ($values['service_interest'] ?: '-'),
      '',
      'Message:',
      trim((string) $values['message']),
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
      $values['email'],
      TRUE,
    );

    if (!empty($result['result'])) {
      $this->messenger()->addStatus($this->t('Thank you. Your message has been sent. We will get back to you soon.'));
      $form_state->setRebuild(FALSE);
      return;
    }

    $this->loggerChannelFactory->get('dates_forms')->error('Contact inquiry email failed for %email.', ['%email' => $values['email']]);
    $this->messenger()->addError($this->t('Sorry, we could not send your message right now. Please try again later.'));
  }

}
