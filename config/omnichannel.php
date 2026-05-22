<?php

return [

    /*
    | Antrian Redis/database untuk job otomasi flow inbox (ProcessOmniFlowJob).
    | Sertakan nama ini di Supervisor: --queue=omnichannel,notifications,...
    | Jangan pakai "default" — bentrok dengan job lain di server yang sama.
    */
    'flow_queue' => env('OMNI_FLOW_QUEUE', 'omnichannel'),

    /** Notifikasi push hanya untuk pesan masuk sync dalam N menit terakhir (hindari spam saat impor riwayat). */
    'instagram_sync_notify_within_minutes' => (int) env('OMNI_INSTAGRAM_SYNC_NOTIFY_WITHIN_MINUTES', 30),

    /** Min interval sync IG saat inbox web dibuka / di-poll (detik). */
    'instagram_inbox_poll_sync_seconds' => (int) env('OMNI_INSTAGRAM_POLL_SYNC_SECONDS', 30),

    /*
    | AI Writing Assistant di composer inbox (grammar, tone, translate ID/EN, dll.)
    | Provider kosong = GOOGLE_REVIEW_AI_PROVIDER → GUEST_COMMENT_AI_PROVIDER → AI_PROVIDER.
    | Model Gemini kosong = GOOGLE_REVIEW_GEMINI_MODEL → GUEST_COMMENT_GEMINI_MODEL → GEMINI_MODEL.
    | API key = GOOGLE_GEMINI_API_KEY (sama Guest Comment / Google Review).
    */
    'ai_writing' => [
        'enabled' => filter_var(env('OMNI_AI_WRITING_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
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
