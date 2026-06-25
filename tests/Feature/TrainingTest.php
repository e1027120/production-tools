<?php

use App\Models\Church;
use App\Models\Training;
use App\Models\TrainingAssignment;
use App\Models\TrainingAttempt;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('users without trainings module access are blocked', function () {
    $user = User::factory()->create();
    $church = Church::create(['name' => 'First Church']);

    // User role with NO trainings module
    $church->users()->attach($user->id, [
        'role' => 'User',
        'modules' => ['racks'], // only racks
    ]);

    $user->update(['current_church_id' => $church->id]);
    $this->actingAs($user);

    $response = $this->get(route('trainings.index'));
    $response->assertStatus(403);
});

test('admins and managers have training index access automatically', function () {
    $user = User::factory()->create();
    $church = Church::create(['name' => 'First Church']);

    // Manager role
    $church->users()->attach($user->id, [
        'role' => 'Manager',
        'modules' => [], // empty modules, but manager gets implicit access
    ]);

    $user->update(['current_church_id' => $church->id]);
    $this->actingAs($user);

    $response = $this->get(route('trainings.index'));
    $response->assertOk();
});

test('admins and managers can create a training with steps and questions', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $church = Church::create(['name' => 'First Church']);
    $church->users()->attach($user->id, ['role' => 'Admin']);
    $user->update(['current_church_id' => $church->id]);
    $this->actingAs($user);

    $image = UploadedFile::fake()->image('step.jpg');
    $audio = UploadedFile::fake()->create('step.mp3', 500, 'audio/mpeg');

    $response = $this->post(route('trainings.store'), [
        'title' => 'Soundboard Operation',
        'description' => 'Learn how to mix audio',
        'ministry' => 'Audio',
        'has_test' => true,
        'passing_score' => 80,
        'steps' => [
            [
                'title' => 'Step 1: Powering On',
                'content' => '<p>Turn on power conditioner</p>',
                'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                'image_file' => $image,
                'audio_file' => $audio,
            ],
        ],
        'questions' => [
            [
                'question_text' => 'What is Step 1?',
                'type' => 'single',
                'options' => [
                    ['option_text' => 'Turn on power', 'is_correct' => true],
                    ['option_text' => 'Turn off power', 'is_correct' => false],
                ],
            ],
        ],
    ]);

    $response->assertRedirect(route('trainings.index'));

    $training = Training::where('title', 'Soundboard Operation')->first();
    expect($training)->not->toBeNull();
    expect($training->steps()->count())->toBe(1);
    expect($training->testQuestions()->count())->toBe(1);

    $step = $training->steps->first();
    expect($step->image_path)->not->toBeNull();
    expect($step->audio_path)->not->toBeNull();
    Storage::disk('public')->assertExists($step->image_path);
    Storage::disk('public')->assertExists($step->audio_path);

    $question = $training->testQuestions->first();
    expect($question->options()->count())->toBe(2);
    expect($question->options()->where('is_correct', true)->first()->option_text)->toBe('Turn on power');
});

test('non-managers cannot create trainings', function () {
    $user = User::factory()->create();
    $church = Church::create(['name' => 'First Church']);
    $church->users()->attach($user->id, [
        'role' => 'User',
        'modules' => ['trainings'],
    ]);
    $user->update(['current_church_id' => $church->id]);
    $this->actingAs($user);

    $response = $this->post(route('trainings.store'), [
        'title' => 'Unauthorized Training',
        'has_test' => false,
        'passing_score' => 80,
        'steps' => [
            ['title' => 'Only step', 'content' => 'content'],
        ],
    ]);

    $response->assertStatus(403);
    expect(Training::where('title', 'Unauthorized Training')->exists())->toBeFalse();
});

test('admins can assign training and users can play and submit answers', function () {
    $admin = User::factory()->create();
    $member = User::factory()->create();
    $church = Church::create(['name' => 'First Church']);

    $church->users()->attach($admin->id, ['role' => 'Admin']);
    $church->users()->attach($member->id, ['role' => 'User', 'modules' => ['trainings']]);

    $admin->update(['current_church_id' => $church->id]);
    $member->update(['current_church_id' => $church->id]);

    // Create a training
    $training = Training::create([
        'church_id' => $church->id,
        'title' => 'Basic Camera Work',
        'has_test' => true,
        'passing_score' => 75,
        'created_by' => $admin->id,
    ]);

    $training->steps()->create([
        'title' => 'Camera Setup',
        'content' => 'Step description',
    ]);

    $question = $training->testQuestions()->create([
        'question_text' => 'Which option is correct?',
        'type' => 'single',
    ]);

    $correctOption = $question->options()->create(['option_text' => 'Right Answer', 'is_correct' => true]);
    $wrongOption = $question->options()->create(['option_text' => 'Wrong Answer', 'is_correct' => false]);

    // 1. Admin assigns training
    $this->actingAs($admin);
    $response = $this->post(route('trainings.assign', $training), [
        'user_ids' => [$member->id],
        'due_at' => now()->addDays(7)->toDateString(),
    ]);
    $response->assertRedirect();

    $assignment = TrainingAssignment::where('training_id', $training->id)->where('user_id', $member->id)->first();
    expect($assignment)->not->toBeNull();
    expect($assignment->status)->toBe('pending');

    // 2. Member plays and submits correct answer
    $this->actingAs($member);
    $response = $this->get(route('trainings.play', $training));
    $response->assertOk();

    $response = $this->post(route('trainings.submit', $training), [
        'answers' => [
            $question->id => $correctOption->id,
        ],
    ]);
    $response->assertRedirect(route('trainings.index'));

    $assignment->refresh();
    expect($assignment->status)->toBe('completed');

    $attempt = TrainingAttempt::where('training_id', $training->id)->where('user_id', $member->id)->first();
    expect($attempt)->not->toBeNull();
    expect($attempt->passed)->toBeTrue();
    expect($attempt->score)->toBe(100);

    // 3. Submit wrong answer on subsequent try (assignment updates to failed)
    $response = $this->post(route('trainings.submit', $training), [
        'answers' => [
            $question->id => $wrongOption->id,
        ],
    ]);

    $assignment->refresh();
    expect($assignment->status)->toBe('failed');
    expect(TrainingAttempt::where('training_id', $training->id)->where('user_id', $member->id)->count())->toBe(2);
});

test('users can complete a training that has no test', function () {
    $admin = User::factory()->create();
    $member = User::factory()->create();
    $church = Church::create(['name' => 'First Church']);

    $church->users()->attach($admin->id, ['role' => 'Admin']);
    $church->users()->attach($member->id, ['role' => 'User', 'modules' => ['trainings']]);

    $admin->update(['current_church_id' => $church->id]);
    $member->update(['current_church_id' => $church->id]);

    // Create a training with has_test = false
    $training = Training::create([
        'church_id' => $church->id,
        'title' => 'Console Cleanliness',
        'has_test' => false,
        'passing_score' => 80,
        'created_by' => $admin->id,
    ]);

    $training->steps()->create([
        'title' => 'Wipe surface',
        'content' => 'Use microfiber cloth',
    ]);

    // Admin assigns training
    $this->actingAs($admin);
    $this->post(route('trainings.assign', $training), [
        'user_ids' => [$member->id],
    ]);

    $assignment = TrainingAssignment::where('training_id', $training->id)->where('user_id', $member->id)->first();
    expect($assignment->status)->toBe('pending');

    // Member completes training
    $this->actingAs($member);
    $response = $this->post(route('trainings.submit', $training), [
        'answers' => [], // empty answers submitted
    ]);

    $response->assertRedirect(route('trainings.index'));

    $assignment->refresh();
    expect($assignment->status)->toBe('completed');

    $attempt = TrainingAttempt::where('training_id', $training->id)->where('user_id', $member->id)->first();
    expect($attempt)->not->toBeNull();
    expect($attempt->passed)->toBeTrue();
    expect($attempt->score)->toBe(100);
});

test('re-assigning a training resets previous attempts and sets status to pending', function () {
    $admin = User::factory()->create();
    $member = User::factory()->create();
    $church = Church::create(['name' => 'First Church']);

    $church->users()->attach($admin->id, ['role' => 'Admin']);
    $church->users()->attach($member->id, ['role' => 'User', 'modules' => ['trainings']]);

    $admin->update(['current_church_id' => $church->id]);
    $member->update(['current_church_id' => $church->id]);

    // Create training
    $training = Training::create([
        'church_id' => $church->id,
        'title' => 'Advanced Soundboards',
        'has_test' => false,
        'passing_score' => 80,
        'created_by' => $admin->id,
    ]);

    $training->steps()->create([
        'title' => 'Mixing slide',
        'content' => 'Fader details',
    ]);

    // First assign
    $this->actingAs($admin);
    $this->post(route('trainings.assign', $training), [
        'user_ids' => [$member->id],
    ]);

    // Member completes first time
    $this->actingAs($member);
    $this->post(route('trainings.submit', $training), [
        'answers' => [],
    ]);

    // Verify first attempt completed
    $assignment = TrainingAssignment::where('training_id', $training->id)->where('user_id', $member->id)->first();
    expect($assignment->status)->toBe('completed');
    expect(TrainingAttempt::where('training_id', $training->id)->where('user_id', $member->id)->count())->toBe(1);

    // Admin re-assigns training
    $this->actingAs($admin);
    $response = $this->post(route('trainings.assign', $training), [
        'user_ids' => [$member->id],
        'due_at' => now()->addDays(5)->toDateString(),
    ]);

    // Verify reset
    $assignment->refresh();
    expect($assignment->status)->toBe('pending');
    expect(TrainingAttempt::where('training_id', $training->id)->where('user_id', $member->id)->count())->toBe(0);
});

test('admins can create a user manual and it automatically generates a share token', function () {
    $user = User::factory()->create();
    $church = Church::create(['name' => 'First Church']);
    $church->users()->attach($user->id, ['role' => 'Admin']);
    $user->update(['current_church_id' => $church->id]);
    $this->actingAs($user);

    $response = $this->post(route('trainings.store'), [
        'type' => 'user_manual',
        'title' => 'Video Switcher Manual',
        'description' => 'Instructions for Blackmagic ATEM',
        'ministry' => 'Video',
        'has_test' => false,
        'passing_score' => 80,
        'steps' => [
            [
                'title' => 'Step 1: Check inputs',
                'content' => '<p>Inspect SDI cables</p>',
            ],
        ],
    ]);

    $response->assertRedirect(route('trainings.index'));

    $manual = Training::where('title', 'Video Switcher Manual')->first();
    expect($manual)->not->toBeNull();
    expect($manual->type)->toBe('user_manual');
    expect($manual->share_token)->not->toBeNull();
    expect($manual->has_test)->toBeFalse();
});

test('admins can toggle public sharing on user manuals', function () {
    $admin = User::factory()->create();
    $church = Church::create(['name' => 'First Church']);
    $church->users()->attach($admin->id, ['role' => 'Admin']);
    $admin->update(['current_church_id' => $church->id]);

    $manual = Training::create([
        'church_id' => $church->id,
        'type' => 'user_manual',
        'title' => 'Projection Manual',
        'has_test' => false,
        'passing_score' => 80,
        'share_token' => 'custom-token-123',
        'created_by' => $admin->id,
    ]);

    $this->actingAs($admin);

    // Toggle off (removes token)
    $response = $this->post(route('trainings.toggle-share', $manual));
    $response->assertRedirect();
    $manual->refresh();
    expect($manual->share_token)->toBeNull();

    // Toggle on (adds token)
    $response = $this->post(route('trainings.toggle-share', $manual));
    $response->assertRedirect();
    $manual->refresh();
    expect($manual->share_token)->not->toBeNull();
    expect(strlen($manual->share_token))->toBe(32);
});

test('guests can view public simplified checklist using share token without login', function () {
    $admin = User::factory()->create();
    $church = Church::create(['name' => 'First Church']);
    $church->users()->attach($admin->id, ['role' => 'Admin']);

    $manual = Training::create([
        'church_id' => $church->id,
        'type' => 'user_manual',
        'title' => 'Lighting Desk Setup',
        'has_test' => false,
        'passing_score' => 80,
        'share_token' => 'public-share-token-xyz-987',
        'created_by' => $admin->id,
    ]);

    $manual->steps()->create([
        'title' => 'Fader Calibration',
        'content' => 'Fader instructions text',
    ]);

    // View as guest (no actingAs)
    $response = $this->get(route('trainings.shared.view', 'public-share-token-xyz-987'));
    $response->assertOk();
    $response->assertSee('Lighting Desk Setup');
    $response->assertSee('Fader Calibration');
});
