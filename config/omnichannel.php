<?php

return [

    /*
    | Antrian Redis/database untuk job otomasi flow inbox (ProcessOmniFlowJob).
    | Sertakan nama ini di Supervisor: --queue=omnichannel,notifications,...
    | Jangan pakai "default" — bentrok dengan job lain di server yang sama.
    */
    'flow_queue' => env('OMNI_FLOW_QUEUE', 'omnichannel'),

];
