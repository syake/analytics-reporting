<header class="navbar navbar-light navbar-toggleable-md bd-navbar fixed-top">
  <nav class="container">
    <button class="navbar-toggler navbar-toggler-right" type="button" data-toggle="collapse" data-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <a class="navbar-brand" href="./">Reporting API v4 Demo</a>
    <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
      <ul class="nav navbar-nav">
        <li class="nav-item"><a class="nav-item nav-link" href="api" target="_blank">API<i class="fa fa-external-link" aria-hidden="true"></i></a></li>
        <li class="nav-item<?php if ($request == 'day') print(' active'); ?>"><a class="nav-item nav-link" href="day">Day</a></li>
        <li class="nav-item<?php if ($request == 'week') print(' active'); ?>"><a class="nav-item nav-link" href="week">Week</a></li>
        <li class="nav-item<?php if ($request == 'month') print(' active'); ?>"><a class="nav-item nav-link" href="month">Month</a></li>
        <li class="nav-item<?php if ($request == 'page') print(' active'); ?>"><a class="nav-item nav-link" href="page">Page</a></li>
        <li class="nav-item<?php if ($request == 'region') print(' active'); ?>"><a class="nav-item nav-link" href="region">Region</a></li>
      </ul>
    </div>
  </nav>
</header>
