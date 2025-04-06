<?php

namespace Drupal\daily_progress_tracker\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Url;
use Drupal\Core\Database\DatabaseException;

/**
 * Provides a form for adding and editing tasks.
 */
class TaskForm extends FormBase {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The task being edited, if any.
   *
   * @var object|null
   */
  protected $task;

  /**
   * Constructs a TaskForm object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(Connection $database) {
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'daily_progress_tracker_task_form';
  }

  /**
   * Loads a task from the database.
   *
   * @param int $id
   *   The task ID to load.
   *
   * @return object|false
   *   The task object or FALSE if not found.
   */
  protected function loadTask($id) {
    try {
      return $this->database->select('daily_progress_tasks', 't')
        ->fields('t')
        ->condition('id', $id)
        ->execute()
        ->fetchObject();
    }
    catch (\Exception $e) {
      $this->logger('daily_progress_tracker')->error('Error loading task @id: @error', [
        '@id' => $id,
        '@error' => $e->getMessage(),
      ]);
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $id = NULL) {
    // Load existing task if we're editing.
    if ($id) {
      $this->task = $this->loadTask($id);
      if (!$this->task) {
        $this->messenger()->addError($this->t('Unable to load task for editing.'));
        return $this->redirect('daily_progress_tracker.task_list');
      }
    }

    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Task Title'),
      '#required' => TRUE,
      '#maxlength' => 255,
      '#default_value' => $this->task ? $this->task->title : '',
    ];

    $form['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#description' => $this->t('Optional description of the task.'),
      '#default_value' => $this->task ? $this->task->description : '',
    ];

    $form['start_date'] = [
      '#type' => 'date',
      '#title' => $this->t('Start Date'),
      '#required' => TRUE,
      '#default_value' => $this->task 
        ? date('Y-m-d', $this->task->start_date)
        : date('Y-m-d'),
    ];

    $form['end_date'] = [
      '#type' => 'date',
      '#title' => $this->t('End Date'),
      '#required' => TRUE,
      '#default_value' => $this->task
        ? date('Y-m-d', $this->task->end_date)
        : date('Y-m-d', strtotime('+1 month')),
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->task ? $this->t('Update Task') : $this->t('Save Task'),
    ];

    if ($this->task) {
      $form['actions']['delete'] = [
        '#type' => 'link',
        '#title' => $this->t('Delete'),
        '#url' => Url::fromRoute('daily_progress_tracker.task_delete', ['id' => $this->task->id]),
        '#attributes' => [
          'class' => ['button', 'button--danger'],
        ],
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    // Validate dates.
    $start_date = strtotime($form_state->getValue('start_date'));
    $end_date = strtotime($form_state->getValue('end_date'));

    if ($end_date < $start_date) {
      $form_state->setError($form['end_date'], $this->t('End date must be after the start date.'));
    }

    // Validate title length.
    $title = trim($form_state->getValue('title'));
    if (strlen($title) < 3) {
      $form_state->setError($form['title'], $this->t('Task title must be at least 3 characters long.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    
    $fields = [
      'title' => trim($values['title']),
      'description' => trim($values['description']),
      'start_date' => strtotime($values['start_date']),
      'end_date' => strtotime($values['end_date']),
    ];

    $transaction = $this->database->startTransaction();
    try {
      if ($this->task) {
        // Update existing task.
        $this->database->update('daily_progress_tasks')
          ->fields($fields)
          ->condition('id', $this->task->id)
          ->execute();

        $this->messenger()->addMessage($this->t('Task "@title" has been updated.', [
          '@title' => $fields['title'],
        ]));

        // Log the update
        $this->logger('daily_progress_tracker')->notice('Task @id (@title) was updated by @uid', [
          '@id' => $this->task->id,
          '@title' => $fields['title'],
          '@uid' => $this->currentUser()->id(),
        ]);
      }
      else {
        // Create new task.
        $fields['created'] = \Drupal::time()->getRequestTime();
        
        $id = $this->database->insert('daily_progress_tasks')
          ->fields($fields)
          ->execute();

        $this->messenger()->addMessage($this->t('Task "@title" has been created.', [
          '@title' => $fields['title'],
        ]));

        // Log the creation
        $this->logger('daily_progress_tracker')->notice('New task @id (@title) was created by @uid', [
          '@id' => $id,
          '@title' => $fields['title'],
          '@uid' => $this->currentUser()->id(),
        ]);
      }
    }
    catch (DatabaseException $e) {
      $transaction->rollBack();
      $this->messenger()->addError($this->t('An error occurred while saving the task. The changes have not been saved.'));
      $this->logger('daily_progress_tracker')->error('Database error while saving task: @error', [
        '@error' => $e->getMessage(),
      ]);
      return;
    }
    catch (\Exception $e) {
      $transaction->rollBack();
      $this->messenger()->addError($this->t('An unexpected error occurred while saving the task. The changes have not been saved.'));
      $this->logger('daily_progress_tracker')->error('Error saving task: @error', [
        '@error' => $e->getMessage(),
      ]);
      return;
    }

    $form_state->setRedirect('daily_progress_tracker.task_list');
  }
}