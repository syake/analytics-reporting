<header class="navbar navbar-light navbar-toggleable-md bd-navbar fixed-top">
  <nav class="container">
    <a class="navbar-brand" href="./">Reporting API v4 Demo</a>
    <div class="navbar-collapse" >
      <ul class="nav navbar-nav">
        <li class="nav-item<?php if ($request == 'day') print(' active'); ?>"><a class="nav-item nav-link" href="day">Day</a></li>
        <li class="nav-item<?php if ($request == 'week') print(' active'); ?>"><a class="nav-item nav-link" href="week">Week</a></li>
        <li class="nav-item<?php if ($request == 'month') print(' active'); ?>"><a class="nav-item nav-link" href="month">Month</a></li>
        <li class="nav-item<?php if ($request == 'page') print(' active'); ?>"><a class="nav-item nav-link" href="page">Page</a></li>
        <li class="nav-item<?php if ($request == 'region') print(' active'); ?>"><a class="nav-item nav-link" href="region">Region</a></li>
      </ul>
    </div>
  </nav>
</header>
