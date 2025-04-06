<?php

namespace Drupal\daily_progress_tracker\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Url;
use Drupal\Core\Database\Connection;
use Drupal\Component\Datetime\TimeInterface;

/**
 * Controller for the Daily Progress Tracker module.
 */
class DailyProgressController extends ControllerBase {

  /**
   * The date formatter service.
   */
  protected $dateFormatter;

  /**
   * The database connection.
   */
  protected $database;

  /**
   * The time service.
   */
  protected $time;

  /**
   * Constructs a new DailyProgressController.
   */
  public function __construct(
    DateFormatterInterface $date_formatter,
    Connection $database,
    TimeInterface $time
  ) {
    $this->dateFormatter = $date_formatter;
    $this->database = $database;
    $this->time = $time;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('date.formatter'),
      $container->get('database'),
      $container->get('datetime.time')
    );
  }

  /**
   * Displays the list of tasks.
   */
  public function taskList() {
    $build = [];
    $build['#title'] = $this->t('Daily Progress Tracker');

    // Tasks section
    $build['tasks_section'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['tasks-section']],
    ];

    // Add Task button
    $build['tasks_section']['add_task'] = [
      '#type' => 'link',
      '#title' => $this->t('Add new task'),
      '#url' => Url::fromRoute('daily_progress_tracker.task_add'),
      '#attributes' => [
        'class' => ['button', 'button--primary', 'button--add-task'],
      ],
    ];

    // Get active tasks
    $tasks = $this->getActiveTasks();

    // Tasks table
    $build['tasks_section']['tasks'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Task'),
        $this->t('Timeframe'),
        $this->t('Progress'),
        $this->t('Operations'),
      ],
      '#rows' => [],
      '#empty' => $this->t('No tasks found. Add a new task to get started.'),
      '#attributes' => ['class' => ['tasks-table']],
    ];

    // Populate table rows
    foreach ($tasks as $task) {
      $build['tasks_section']['tasks']['#rows'][] = [
        'data' => [
          'task' => ['data' => ['#markup' => $task->title]],
          'timeframe' => ['data' => ['#markup' => $this->formatTimeframe($task)]],
          'progress' => ['data' => ['#markup' => $this->formatProgress($task)]],
          'operations' => [
            'data' => [
              '#type' => 'operations',
              '#links' => $this->getOperationLinks($task),
            ],
          ],
        ],
        'class' => ['task-row'],
      ];
    }

    return $build;
  }

  /**
   * Gets all active tasks.
   */
  protected function getActiveTasks() {
    try {
      return $this->database->select('daily_progress_tasks', 't')
        ->fields('t')
        ->condition('end_date', $this->time->getRequestTime(), '>')
        ->orderBy('start_date', 'DESC')
        ->execute()
        ->fetchAll();
    }
    catch (\Exception $e) {
      $this->logger('daily_progress_tracker')->error('Error loading tasks: @error', [
        '@error' => $e->getMessage(),
      ]);
      return [];
    }
  }

  /**
   * Formats the timeframe for display.
   */
  protected function formatTimeframe($task) {
    return $this->t('@start to @end', [
      '@start' => $this->dateFormatter->format($task->start_date, 'short'),
      '@end' => $this->dateFormatter->format($task->end_date, 'short'),
    ]);
  }

  /**
   * Formats the progress for display.
   */
  protected function formatProgress($task) {
    $total_days = ($task->end_date - $task->start_date) / (24 * 60 * 60);
    
    try {
      $completed_days = $this->database->select('daily_progress_checkins', 'c')
        ->condition('task_id', $task->id)
        ->condition('completed', 1)
        ->countQuery()
        ->execute()
        ->fetchField();

      $percentage = ($completed_days / max(1, $total_days)) * 100;

      return $this->t('@completed/@total days (@percentage%)', [
        '@completed' => $completed_days,
        '@total' => ceil($total_days),
        '@percentage' => number_format($percentage, 1),
      ]);
    }
    catch (\Exception $e) {
      $this->logger('daily_progress_tracker')->error('Error calculating progress: @error', [
        '@error' => $e->getMessage(),
      ]);
      return $this->t('N/A');
    }
  }

  /**
   * Gets operation links for a task.
   */
  protected function getOperationLinks($task) {
    return [
      'edit' => [
        'title' => $this->t('Edit'),
        'url' => Url::fromRoute('daily_progress_tracker.task_edit', ['id' => $task->id]),
      ],
      'delete' => [
        'title' => $this->t('Delete'),
        'url' => Url::fromRoute('daily_progress_tracker.task_delete', ['id' => $task->id]),
      ],
      'checkin' => [
        'title' => $this->t('Check-in'),
        'url' => Url::fromRoute('daily_progress_tracker.daily_checkin', ['id' => $task->id]),
      ],
    ];
  }
}