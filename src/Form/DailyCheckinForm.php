<?php

namespace Drupal\daily_progress_tracker\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Component\Datetime\TimeInterface;

/**
 * Provides a form for daily check-ins.
 */
class DailyCheckinForm extends FormBase {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Constructs a DailyCheckinForm object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(Connection $database, TimeInterface $time) {
    $this->database = $database;
    $this->time = $time;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('datetime.time')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'daily_progress_tracker_checkin_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $id = NULL) {
    $task = $this->database->select('daily_progress_tasks', 't')
      ->fields('t')
      ->condition('id', $id)
      ->execute()
      ->fetchObject();

    if (!$task) {
      $this->messenger()->addError($this->t('Task not found.'));
      return $this->redirect('daily_progress_tracker.task_list');
    }

    $form['task_id'] = [
      '#type' => 'value',
      '#value' => $task->id,
    ];

    $form['date'] = [
      '#type' => 'date',
      '#title' => $this->t('Check-in Date'),
      '#required' => TRUE,
      '#default_value' => date('Y-m-d'),
    ];

    $form['completed'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Task completed for this day'),
      '#default_value' => 0,
    ];

    $form['notes'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Notes'),
      '#rows' => 3,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save Check-in'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    try {
      $values = $form_state->getValues();
      
      // Convert the date to a timestamp
      $date = strtotime($values['date']);
      
      // Ensure completed is either 0 or 1
      $completed = !empty($values['completed']) ? 1 : 0;
      
      // Prepare the fields for update/insert
      $fields = [
        'completed' => $completed,
        'notes' => $values['notes'],
      ];

      // Try to update existing record first
      $query = $this->database->merge('daily_progress_checkins')
        ->key([
          'task_id' => $values['task_id'],
          'date' => $date,
        ])
        ->fields($fields);

      $query->execute();

      $this->messenger()->addMessage($this->t('Check-in has been saved.'));
    }
    catch (\Exception $e) {
      $this->messenger()->addError($this->t('An error occurred while saving the check-in.'));
      $this->logger('daily_progress_tracker')->error('Error saving check-in: @error', [
        '@error' => $e->getMessage(),
      ]);
    }

    $form_state->setRedirect('daily_progress_tracker.task_list');
  }

}