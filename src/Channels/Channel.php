<?php

namespace Errly\LaravelErrly\Channels;

use Errly\LaravelErrly\ErrorReport;

interface Channel
{
    /**
     * Deliver the report. A channel that is not set up returns quietly.
     */
    public function send(ErrorReport $report): void;
}
