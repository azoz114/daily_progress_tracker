<?php

namespace Drupal\daily_progress_tracker\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

class ChartManager {
  use StringTranslationTrait;

  protected $database;
  protected $dateFormatter;
  protected $cache;

  public function __construct(
    Connection $database,
    DateFormatterInterface $date_formatter,
    CacheBackendInterface $cache
  ) {
    $this->database = $database;
    $this->dateFormatter = $date_formatter;
    $this->cache = $cache;
  }

  /**
   * Ensures complete chart data structure for Billboard.
   */
  protected function prepareChartStructure(array $chart): array {
    $defaults = [
      '#type' => 'chart',
      '#chart_type' => 'bar',
      '#chart_library' => 'charts',
      '#title' => '',
      '#data' => [],
      '#labels' => [],
      '#raw_options' => [
        'data' => [
          'labels' => [],
          'datasets' => []
        ],
        'responsive' => TRUE,
        'maintainAspectRatio' => FALSE
      ]
    ];

    $chart = array_merge($defaults, $chart);

    // Ensure each data series is properly formatted
    foreach ($chart['#data'] as &$series) {
      $series += [
        '#type' => 'chart_data',
        '#title' => '',
        '#data' => [],
        '#labels' => []
      ];
    }

    return $chart;
  }

  public function getOverallProgressChart() {
    $cid = 'daily_progress:overall_chart';
    
    if ($cache = $this->cache->get($cid)) {
      return $this->prepareChartStructure($cache->data);
    }

    $chart = [
      '#title' => $this->t('Overall Task Progress'),
      '#chart_type' => 'bar',
      '#raw_options' => [
        'scales' => [
          'y' => ['min' => 0, 'max' => 100]
        ]
      ]
    ];

    $tasks = $this->database->select('daily_progress_tasks', 't')
      ->fields('t', ['id', 'title', 'start_date', 'end_date'])
      ->condition('end_date', time(), '>')
      ->execute()
      ->fetchAll();

    if (!empty($tasks)) {
      $chart['#data'][] = [
        '#title' => $this->t('Completion %'),
        '#data' => array_map([$this, 'calculateTaskProgress'], $tasks),
      ];
      $chart['#labels'] = array_column($tasks, 'title');
    }

    $chart = $this->prepareChartStructure($chart);
    $this->cache->set($cid, $chart, CacheBackendInterface::CACHE_PERMANENT, ['daily_progress_charts']);
    
    return $chart;
  }

  public function getTaskProgressChart($task) {
    $cid = "daily_progress:task_chart:{$task->id}";
    
    if ($cache = $this->cache->get($cid)) {
      return $this->prepareChartStructure($cache->data);
    }

    $chart = [
      '#title' => $this->t('Progress: @task', ['@task' => $task->title]),
      '#chart_type' => 'line'
    ];

    $checkins = $this->database->select('daily_progress_checkins', 'c')
      ->fields('c', ['date', 'completed'])
      ->condition('task_id', $task->id)
      ->orderBy('date')
      ->execute()
      ->fetchAll();

    $cumulative = 0;
    $series_data = [];
    $labels = [];
    
    foreach ($checkins as $checkin) {
      $cumulative += (int)$checkin->completed;
      $series_data[] = $cumulative;
      $labels[] = $this->dateFormatter->format($checkin->date, 'custom', 'M j');
    }

    $chart['#data'][] = [
      '#title' => $this->t('Completed Days'),
      '#data' => $series_data
    ];
    $chart['#labels'] = $labels;
    
    $chart = $this->prepareChartStructure($chart);
    $this->cache->set($cid, $chart, CacheBackendInterface::CACHE_PERMANENT, ['daily_progress_charts']);
    
    return $chart;
  }

  protected function calculateTaskProgress($task) {
    $total_days = ($task->end_date - $task->start_date) / 86400;
    $completed = $this->database->select('daily_progress_checkins', 'c')
      ->condition('task_id', $task->id)
      ->condition('completed', 1)
      ->countQuery()
      ->execute()
      ->fetchField();

    return ($completed / max(1, $total_days)) * 100;
  }
}