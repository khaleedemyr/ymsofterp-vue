<?php

return [

    /*
    | Antrian Redis/database untuk job otomasi flow inbox (ProcessOmniFlowJob).
    | Sertakan nama ini di Supervisor: --queue=omnichannel,notifications,...
    | Jangan pakai "default" — bentrok dengan job lain di server yang sama.
    */
    'flow_queue' => env('OMNI_FLOW_QUEUE', 'omnichannel'),

    /*
    | AI Writing Assistant di composer inbox (grammar, tone, translate ID/EN, dll.)
    | Provider kosong = GOOGLE_REVIEW_AI_PROVIDER → GUEST_COMMENT_AI_PROVIDER → AI_PROVIDER.
    | Model Gemini kosong = GOOGLE_REVIEW_GEMINI_MODEL → GUEST_COMMENT_GEMINI_MODEL → GEMINI_MODEL.
    | API key = GOOGLE_GEMINI_API_KEY (sama Guest Comment / Google Review).
    */
    'ai_writing' => [
        'enabled' => env('OMNI_AI_WRITING_ENABLED', true),
        'provider' => env('OMNI_AI_PROVIDER'),
        'gemini_model' => env('OMNI_AI_GEMINI_MODEL'),
        'claude_model' => env('OMNI_AI_CLAUDE_MODEL'),
        'openai_model' => env('OMNI_AI_OPENAI_MODEL'),
        'temperature' => (float) env('OMNI_AI_TEMPERATURE', 0.4),
        'max_tokens' => (int) env('OMNI_AI_MAX_TOKENS', 2048),
        'timeout' => (int) env('OMNI_AI_TIMEOUT', 60),
        'include_context' => env('OMNI_AI_INCLUDE_CONTEXT', true),
    ],

];
