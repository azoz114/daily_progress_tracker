<?php

namespace Drupal\daily_progress_tracker\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Database\Connection;

/**
 * Provides a confirmation form for deleting a task.
 */
class TaskDeleteForm extends ConfirmFormBase {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The task being deleted.
   *
   * @var object|null
   */
  protected $task;

  /**
   * The number of check-ins associated with this task.
   *
   * @var int
   */
  protected $checkInCount;

  /**
   * Constructs a TaskDeleteForm object.
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
    return 'daily_progress_tracker_task_delete_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $id = NULL) {
    $this->task = $this->database->select('daily_progress_tasks', 't')
      ->fields('t')
      ->condition('id', $id)
      ->execute()
      ->fetchObject();

    if (!$this->task) {
      $this->messenger()->addError($this->t('Task not found.'));
      return $this->redirect('daily_progress_tracker.task_list');
    }

    // Count associated check-ins.
    $this->checkInCount = $this->database->select('daily_progress_checkins', 'c')
      ->condition('task_id', $this->task->id)
      ->countQuery()
      ->execute()
      ->fetchField();

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete the task "@title"?', [
      '@title' => $this->task->title,
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    $description = $this->t('This action cannot be undone.');
    
    if ($this->checkInCount > 0) {
      $description .= ' ' . $this->formatPlural(
        $this->checkInCount,
        'This will also permanently delete 1 check-in record associated with this task.',
        'This will also permanently delete @count check-in records associated with this task.'
      );
    }
    
    return $description;
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('daily_progress_tracker.task_list');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    
    $transaction = $this->database->startTransaction();
    try {
      // Delete the task and its check-ins.
      $this->database->delete('daily_progress_checkins')
        ->condition('task_id', $this->task->id)
        ->execute();

      $this->database->delete('daily_progress_tasks')
        ->condition('id', $this->task->id)
        ->execute();

      $this->messenger()->addMessage($this->t('Task "@title" has been deleted.', [
        '@title' => $this->task->title,
      ]));

      if ($this->checkInCount > 0) {
        $this->messenger()->addMessage($this->formatPlural(
          $this->checkInCount,
          '1 associated check-in record was also deleted.',
          '@count associated check-in records were also deleted.'
        ));
      }
    }
    catch (\Exception $e) {
      $transaction->rollBack();
      $this->messenger()->addError($this->t('An error occurred while deleting the task. The task and its check-ins have not been deleted.'));
      $this->logger('daily_progress_tracker')->error('Error deleting task @id: @error', [
        '@id' => $this->task->id,
        '@error' => $e->getMessage(),
      ]);
    }

    $form_state->setRedirect('daily_progress_tracker.task_list');
  }
}