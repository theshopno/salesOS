<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
if (!staff_can('view', 'salesos') || !salesos_ecommerce_enabled()) {
    return;
}
$CI = &get_instance();
$CI->load->model('salesos/salesos_model');
$d = $CI->salesos_model->get_dashboard_widget_data();
?>
<div class="widget relative" id="widget-<?php echo create_widget_id(); ?>" data-name="SalesOS: 7-Day Sales Trend Chart">
    <div class="panel_s" style="border: 1px solid #e2e8f0; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.03); margin-bottom: 20px;">
        <div class="panel-body" style="padding: 16px;">
            <div class="widget-dragger"></div>
            
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; padding-bottom: 8px; border-bottom: 1px solid #f1f5f9;">
                <div>
                    <h4 class="no-margin bold" style="font-size: 14px; color: #0f172a; display: flex; align-items: center; gap: 6px;">
                        <i class="fa fa-line-chart text-primary"></i> ৭ দিনের সেলস ও রেভিনিউ ট্রেন্ড (Omni-Channel Sales)
                    </h4>
                    <span class="text-muted" style="font-size: 11px;">গত ৭ দিনের চ্যানেলভিত্তিক দৈনিক বিক্রয় প্রবাহ</span>
                </div>
                <div style="display: flex; align-items: center; gap: 12px; font-size: 11px;">
                    <span style="color: #059669; font-weight: 600;"><i class="fa fa-circle" style="font-size: 9px;"></i> POS</span>
                    <span style="color: #7c3aed; font-weight: 600;"><i class="fa fa-circle" style="font-size: 9px;"></i> WooCommerce</span>
                    <span style="color: #ea580c; font-weight: 600;"><i class="fa fa-circle" style="font-size: 9px;"></i> Direct / Phone</span>
                </div>
            </div>

            <div style="position: relative; height: 230px; width: 100%;">
                <canvas id="salesos_trend_chart_canvas"></canvas>
            </div>
        </div>
    </div>
</div>

<script>
$(function() {
    var chartCanvas = document.getElementById('salesos_trend_chart_canvas');
    if (chartCanvas && typeof Chart !== 'undefined') {
        new Chart(chartCanvas, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($d['chart_data']['labels']); ?>,
                datasets: [
                    {
                        label: 'POS Counter & Online',
                        data: <?php echo json_encode($d['chart_data']['pos']); ?>,
                        borderColor: '#059669',
                        backgroundColor: 'rgba(5, 150, 105, 0.08)',
                        pointBackgroundColor: '#059669',
                        pointBorderColor: '#fff',
                        pointHoverRadius: 5,
                        pointRadius: 3,
                        borderWidth: 2,
                        fill: true,
                        tension: 0.3
                    },
                    {
                        label: 'WooCommerce Store',
                        data: <?php echo json_encode($d['chart_data']['woo']); ?>,
                        borderColor: '#7c3aed',
                        backgroundColor: 'rgba(124, 58, 237, 0.08)',
                        pointBackgroundColor: '#7c3aed',
                        pointBorderColor: '#fff',
                        pointHoverRadius: 5,
                        pointRadius: 3,
                        borderWidth: 2,
                        fill: true,
                        tension: 0.3
                    },
                    {
                        label: 'Direct / Manual Calls',
                        data: <?php echo json_encode($d['chart_data']['manual']); ?>,
                        borderColor: '#ea580c',
                        backgroundColor: 'rgba(234, 88, 12, 0.08)',
                        pointBackgroundColor: '#ea580c',
                        pointBorderColor: '#fff',
                        pointHoverRadius: 5,
                        pointRadius: 3,
                        borderWidth: 2,
                        fill: true,
                        tension: 0.3
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                legend: {
                    display: false
                },
                tooltips: {
                    mode: 'index',
                    intersect: false,
                    callbacks: {
                        label: function(tooltipItem, data) {
                            var datasetLabel = data.datasets[tooltipItem.datasetIndex].label || '';
                            return datasetLabel + ': ' + Number(tooltipItem.yLabel).toLocaleString() + ' BDT';
                        }
                    }
                },
                scales: {
                    xAxes: [{
                        gridLines: {
                            display: false,
                            drawBorder: false
                        },
                        ticks: {
                            fontSize: 11,
                            fontColor: '#64748b'
                        }
                    }],
                    yAxes: [{
                        gridLines: {
                            color: '#f1f5f9',
                            drawBorder: false
                        },
                        ticks: {
                            beginAtZero: true,
                            fontSize: 11,
                            fontColor: '#64748b',
                            callback: function(value) {
                                return value >= 1000 ? (value / 1000) + 'k BDT' : value + ' BDT';
                            }
                        }
                    }]
                }
            }
        });
    }
});
</script>
