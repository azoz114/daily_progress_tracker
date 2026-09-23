<?php

namespace Drupal\daily_progress_tracker\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\daily_progress_tracker\Service\ChartManager;

/**
 * Provides a 'Daily Progress Dashboard' Block.
 *
 * @Block(
 *   id = "daily_progress_dashboard_block",
 *   admin_label = @Translation("Daily Progress Dashboard"),
 *   category = @Translation("Daily Progress Tracker"),
 * )
 */
class DailyProgressDashboardBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The chart manager service.
   *
   * @var \Drupal\daily_progress_tracker\Service\ChartManager
   */
  protected $chartManager;

  /**
   * Constructs a DailyProgressDashboardBlock object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\daily_progress_tracker\Service\ChartManager $chart_manager
   *   The chart manager service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ChartManager $chart_manager
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->chartManager = $chart_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('daily_progress_tracker.chart_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $chart = $this->chartManager->getOverallProgressChart();

    return [
      '#theme' => 'container',
      '#attributes' => ['class' => ['daily-progress-dashboard-block']],
      'chart' => $chart,
    ];
  }

}