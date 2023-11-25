<?php

require_once('atREST.Core.php');
require_once('atREST.HTTP.php');
require_once('atREST.API.php');
require_once('atREST.Configuration.php');

use atREST\Core;
use atREST\API;

Core::Initialize($atRESTConfguration);
API::HandleRequest();
