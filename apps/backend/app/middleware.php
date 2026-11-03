<?php

return [
    //session开启
    think\middleware\SessionInit::class,
    app\middleware\ObservabilityContext::class,
    app\middleware\EventingRequestDrain::class,
];
