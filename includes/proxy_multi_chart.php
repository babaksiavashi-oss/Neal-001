<!-- =========================
     Proxy Activity Multi Chart
     ========================= -->

<div class="card dashboard-chart-card">

    <div class="section-header">

        <h2>
            📈 جزئیات فعالیت Proxy در ۲۴ ساعت اخیر
        </h2>

        <span class="section-badge">
            24 Hours
        </span>

    </div>


    <div class="proxy-multi-chart">

<?php

$multi_chart_max = 1;

foreach ($proxy_hourly_detail as $hour => $data) {

    if ($data["total"] > $multi_chart_max) {
        $multi_chart_max = $data["total"];
    }

}


foreach ($proxy_hourly_detail as $hour => $data):

    $total_height =
        ($data["total"] / $multi_chart_max) * 100;

    $denied_height =
        ($data["denied"] / $multi_chart_max) * 100;

    $https_height =
        ($data["https"] / $multi_chart_max) * 100;

?>

<div class="multi-chart-column">

    <div class="multi-chart-bars">

        <div class="multi-bar total-bar"
             style="height: <?php echo $total_height; ?>%">
            <span>
                <?php echo number_format($data["total"]); ?>
            </span>
        </div>


        <div class="multi-bar denied-bar"
             style="height: <?php echo $denied_height; ?>%">
            <span>
                <?php echo number_format($data["denied"]); ?>
            </span>
        </div>


        <div class="multi-bar https-bar"
             style="height: <?php echo $https_height; ?>%">
            <span>
                <?php echo number_format($data["https"]); ?>
            </span>
        </div>

    </div>


    <div class="multi-chart-hour">
        <?php echo htmlspecialchars($hour); ?>
    </div>

</div>

<?php endforeach; ?>

    </div>


    <div class="chart-legend">

        <div class="legend-item">
            <span class="legend-dot legend-total"></span>
            Total Requests
        </div>

        <div class="legend-item">
            <span class="legend-dot legend-denied"></span>
            403 Denied
        </div>

        <div class="legend-item">
            <span class="legend-dot legend-https"></span>
            HTTPS 200
        </div>

    </div>

</div>
