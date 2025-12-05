-- Add route names for quiz questions management
-- These routes will be automatically created by Laravel's resource routing
-- but we need to ensure they exist in the route cache

-- The following routes will be created:
-- GET /lms/quizzes/{quiz}/questions - lms.quizzes.questions.index
-- GET /lms/quizzes/{quiz}/questions/create - lms.quizzes.questions.create  
-- POST /lms/quizzes/{quiz}/questions - lms.quizzes.questions.store
-- GET /lms/quizzes/{quiz}/questions/{question}/edit - lms.quizzes.questions.edit
-- PUT/PATCH /lms/quizzes/{quiz}/questions/{question} - lms.quizzes.questions.update
-- DELETE /lms/quizzes/{quiz}/questions/{question} - lms.quizzes.questions.destroy

-- Note: This is just a reference file. The routes are created automatically
-- by the Route::resource() call in routes/web.php
