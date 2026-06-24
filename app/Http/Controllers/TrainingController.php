<?php

namespace App\Http\Controllers;

use App\Models\Training;
use App\Models\TrainingStep;
use App\Models\TestQuestion;
use App\Models\QuestionOption;
use App\Models\TrainingAssignment;
use App\Models\TrainingAttempt;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class TrainingController extends Controller
{
    /**
     * Display training index.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        if (! $user->hasModuleAccess('trainings')) {
            abort(403, 'You do not have access to this module.');
        }

        $churchId = $user->current_church_id;

        // 1. My Assignments
        $assignments = TrainingAssignment::where('user_id', $user->id)
            ->whereHas('training', function ($q) use ($churchId) {
                $q->where('church_id', $churchId);
            })
            ->with(['training', 'assigner'])
            ->get()
            ->map(function ($assign) {
                return [
                    'id' => $assign->id,
                    'training_id' => $assign->training_id,
                    'title' => $assign->training->title,
                    'description' => $assign->training->description,
                    'ministry' => $assign->training->ministry,
                    'due_at' => $assign->due_at?->toIso8601String(),
                    'status' => $assign->status,
                    'assigned_by' => $assign->assigner->name,
                ];
            });

        // 2. My Completed History
        $myHistory = TrainingAttempt::where('user_id', $user->id)
            ->whereHas('training', function ($q) use ($churchId) {
                $q->where('church_id', $churchId);
            })
            ->with('training')
            ->orderBy('completed_at', 'desc')
            ->get()
            ->map(function ($attempt) {
                return [
                    'id' => $attempt->id,
                    'title' => $attempt->training->title,
                    'ministry' => $attempt->training->ministry,
                    'score' => $attempt->score,
                    'passed' => $attempt->passed,
                    'completed_at' => $attempt->completed_at?->toIso8601String(),
                ];
            });

        // 3. Admin / Manager properties
        $trainings = [];
        $churchMembers = [];
        $reports = [];

        if ($user->hasChurchRole(['Admin', 'Manager'])) {
            $trainings = Training::where('church_id', $churchId)
                ->withCount(['steps', 'assignments', 'attempts'])
                ->orderBy('id', 'desc')
                ->get();

            $churchMembers = $user->currentChurch->users()
                ->orderBy('name')
                ->get()
                ->map(fn($m) => ['id' => $m->id, 'name' => $m->name, 'email' => $m->email]);

            $reports = TrainingAttempt::whereHas('training', function ($q) use ($churchId) {
                $q->where('church_id', $churchId);
            })
                ->with(['training', 'user'])
                ->orderBy('completed_at', 'desc')
                ->get()
                ->map(function ($attempt) {
                    return [
                        'id' => $attempt->id,
                        'user_name' => $attempt->user->name,
                        'user_email' => $attempt->user->email,
                        'training_title' => $attempt->training->title,
                        'ministry' => $attempt->training->ministry,
                        'score' => $attempt->score,
                        'passed' => $attempt->passed,
                        'completed_at' => $attempt->completed_at?->toIso8601String(),
                    ];
                });
        }

        return Inertia::render('trainings/Index', [
            'trainings' => $trainings,
            'assignments' => $assignments,
            'myHistory' => $myHistory,
            'churchMembers' => $churchMembers,
            'reports' => $reports,
            'userRole' => $user->currentChurchMember()?->role,
        ]);
    }

    /**
     * Show training builder.
     */
    public function create(Request $request): Response
    {
        $user = $request->user();
        if (! $user->hasModuleAccess('trainings') || ! $user->hasChurchRole(['Admin', 'Manager'])) {
            abort(403, 'Unauthorized.');
        }

        return Inertia::render('trainings/Builder', [
            'training' => null,
        ]);
    }

    /**
     * Store new training workspace with steps & tests.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user->hasModuleAccess('trainings') || ! $user->hasChurchRole(['Admin', 'Manager'])) {
            abort(403, 'Unauthorized.');
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'ministry' => ['nullable', 'string', 'max:255'],
            'has_test' => ['required', 'boolean'],
            'passing_score' => ['required', 'integer', 'min:1', 'max:100'],
            'steps' => ['required', 'array', 'min:1'],
            'steps.*.title' => ['required', 'string', 'max:255'],
            'steps.*.content' => ['nullable', 'string'],
            'steps.*.video_url' => ['nullable', 'string', 'url', 'max:255'],
            'steps.*.image_file' => ['nullable', 'file', 'image', 'max:5120'],
            'steps.*.audio_file' => ['nullable', 'file', 'mimes:mp3,wav,ogg,m4a,aac', 'max:15360'],
            
            // test questions
            'questions' => ['nullable', 'array'],
            'questions.*.question_text' => ['required', 'string'],
            'questions.*.type' => ['required', 'string', 'in:single,multiple'],
            'questions.*.image_file' => ['nullable', 'file', 'image', 'max:5120'],
            'questions.*.options' => ['required_with:questions', 'array', 'min:2'],
            'questions.*.options.*.option_text' => ['nullable', 'string'],
            'questions.*.options.*.is_correct' => ['required', 'boolean'],
            'questions.*.options.*.image_file' => ['nullable', 'file', 'image', 'max:5120'],
        ]);

        DB::transaction(function () use ($user, $validated, $request) {
            // 1. Create Training
            $training = Training::create([
                'church_id' => $user->current_church_id,
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'ministry' => $validated['ministry'] ?? null,
                'has_test' => $validated['has_test'],
                'passing_score' => $validated['passing_score'],
                'created_by' => $user->id,
            ]);

            // 2. Create steps
            foreach ($validated['steps'] as $idx => $stepData) {
                $imagePath = null;
                $audioPath = null;

                // Locate uploaded file objects dynamically from request structure
                if ($request->hasFile("steps.{$idx}.image_file")) {
                    $imagePath = $request->file("steps.{$idx}.image_file")->store('trainings/images', 'public');
                }
                if ($request->hasFile("steps.{$idx}.audio_file")) {
                    $audioPath = $request->file("steps.{$idx}.audio_file")->store('trainings/audio', 'public');
                }

                $training->steps()->create([
                    'title' => $stepData['title'],
                    'content' => $stepData['content'] ?? null,
                    'image_path' => $imagePath,
                    'audio_path' => $audioPath,
                    'video_url' => $stepData['video_url'] ?? null,
                    'sort_order' => $idx,
                ]);
            }

            // 3. Create test questions
            if ($validated['has_test'] && !empty($validated['questions'])) {
                foreach ($validated['questions'] as $qIdx => $qData) {
                    $qImagePath = null;
                    if ($request->hasFile("questions.{$qIdx}.image_file")) {
                        $qImagePath = $request->file("questions.{$qIdx}.image_file")->store('trainings/questions', 'public');
                    }

                    $question = $training->testQuestions()->create([
                        'question_text' => $qData['question_text'],
                        'image_path' => $qImagePath,
                        'type' => $qData['type'],
                        'sort_order' => $qIdx,
                    ]);

                    foreach ($qData['options'] as $oIdx => $oData) {
                        $oImagePath = null;
                        if ($request->hasFile("questions.{$qIdx}.options.{$oIdx}.image_file")) {
                            $oImagePath = $request->file("questions.{$qIdx}.options.{$oIdx}.image_file")->store('trainings/options', 'public');
                        }

                        $question->options()->create([
                            'option_text' => $oData['option_text'] ?? null,
                            'image_path' => $oImagePath,
                            'is_correct' => $oData['is_correct'],
                        ]);
                    }
                }
            }
        });

        return redirect()->route('trainings.index');
    }

    /**
     * Show training editor.
     */
    public function edit(Request $request, Training $training): Response
    {
        $user = $request->user();
        if (! $user->hasModuleAccess('trainings') || ! $user->hasChurchRole(['Admin', 'Manager'])) {
            abort(403, 'Unauthorized.');
        }

        if ($training->church_id !== $user->current_church_id) {
            abort(403, 'Unauthorized.');
        }

        $training->load(['steps', 'testQuestions.options']);

        return Inertia::render('trainings/Builder', [
            'training' => $training,
        ]);
    }

    /**
     * Update training configuration. (Spoofed as POST to support file attachments)
     */
    public function update(Request $request, Training $training): RedirectResponse
    {
        $user = $request->user();
        if (! $user->hasModuleAccess('trainings') || ! $user->hasChurchRole(['Admin', 'Manager'])) {
            abort(403, 'Unauthorized.');
        }

        if ($training->church_id !== $user->current_church_id) {
            abort(403, 'Unauthorized.');
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'ministry' => ['nullable', 'string', 'max:255'],
            'has_test' => ['required', 'boolean'],
            'passing_score' => ['required', 'integer', 'min:1', 'max:100'],
            'steps' => ['required', 'array', 'min:1'],
            'steps.*.id' => ['nullable', 'integer'],
            'steps.*.title' => ['required', 'string', 'max:255'],
            'steps.*.content' => ['nullable', 'string'],
            'steps.*.video_url' => ['nullable', 'string', 'url', 'max:255'],
            'steps.*.image_file' => ['nullable', 'file', 'image', 'max:5120'],
            'steps.*.audio_file' => ['nullable', 'file', 'mimes:mp3,wav,ogg,m4a,aac', 'max:15360'],
            
            // test questions
            'questions' => ['nullable', 'array'],
            'questions.*.id' => ['nullable', 'integer'],
            'questions.*.question_text' => ['required', 'string'],
            'questions.*.type' => ['required', 'string', 'in:single,multiple'],
            'questions.*.image_file' => ['nullable', 'file', 'image', 'max:5120'],
            'questions.*.options' => ['required_with:questions', 'array', 'min:2'],
            'questions.*.options.*.id' => ['nullable', 'integer'],
            'questions.*.options.*.option_text' => ['nullable', 'string'],
            'questions.*.options.*.is_correct' => ['required', 'boolean'],
            'questions.*.options.*.image_file' => ['nullable', 'file', 'image', 'max:5120'],
        ]);

        DB::transaction(function () use ($training, $validated, $request) {
            $training->update([
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'ministry' => $validated['ministry'] ?? null,
                'has_test' => $validated['has_test'],
                'passing_score' => $validated['passing_score'],
            ]);

            // Keep track of preserved IDs to delete the rest
            $keepStepIds = [];
            $keepQuestionIds = [];

            // Update/Create steps
            foreach ($validated['steps'] as $idx => $stepData) {
                $imagePath = null;
                $audioPath = null;

                // Handle file uploads
                if ($request->hasFile("steps.{$idx}.image_file")) {
                    $imagePath = $request->file("steps.{$idx}.image_file")->store('trainings/images', 'public');
                }
                if ($request->hasFile("steps.{$idx}.audio_file")) {
                    $audioPath = $request->file("steps.{$idx}.audio_file")->store('trainings/audio', 'public');
                }

                $fields = [
                    'title' => $stepData['title'],
                    'content' => $stepData['content'] ?? null,
                    'video_url' => $stepData['video_url'] ?? null,
                    'sort_order' => $idx,
                ];

                if ($imagePath) {
                    $fields['image_path'] = $imagePath;
                }
                if ($audioPath) {
                    $fields['audio_path'] = $audioPath;
                }

                if (!empty($stepData['id'])) {
                    $step = TrainingStep::findOrFail($stepData['id']);
                    $step->update($fields);
                    $keepStepIds[] = $step->id;
                } else {
                    $step = $training->steps()->create($fields);
                    $keepStepIds[] = $step->id;
                }
            }

            // Delete removed steps
            $training->steps()->whereNotIn('id', $keepStepIds)->delete();

            // Create/Update questions
            if ($validated['has_test'] && !empty($validated['questions'])) {
                foreach ($validated['questions'] as $qIdx => $qData) {
                    $qImagePath = null;
                    if ($request->hasFile("questions.{$qIdx}.image_file")) {
                        $qImagePath = $request->file("questions.{$qIdx}.image_file")->store('trainings/questions', 'public');
                    }

                    $qFields = [
                        'question_text' => $qData['question_text'],
                        'type' => $qData['type'],
                        'sort_order' => $qIdx,
                    ];

                    if ($qImagePath) {
                        $qFields['image_path'] = $qImagePath;
                    }

                    if (!empty($qData['id'])) {
                        $question = TestQuestion::findOrFail($qData['id']);
                        $question->update($qFields);
                        $keepQuestionIds[] = $question->id;
                    } else {
                        $question = $training->testQuestions()->create($qFields);
                        $keepQuestionIds[] = $question->id;
                    }

                    $keepOptionIds = [];
                    foreach ($qData['options'] as $oIdx => $oData) {
                        $oImagePath = null;
                        if ($request->hasFile("questions.{$qIdx}.options.{$oIdx}.image_file")) {
                            $oImagePath = $request->file("questions.{$qIdx}.options.{$oIdx}.image_file")->store('trainings/options', 'public');
                        }

                        $oFields = [
                            'option_text' => $oData['option_text'] ?? null,
                            'is_correct' => $oData['is_correct'],
                        ];

                        if ($oImagePath) {
                            $oFields['image_path'] = $oImagePath;
                        }

                        if (!empty($oData['id'])) {
                            $option = QuestionOption::findOrFail($oData['id']);
                            $option->update($oFields);
                            $keepOptionIds[] = $option->id;
                        } else {
                            $option = $question->options()->create($oFields);
                            $keepOptionIds[] = $option->id;
                        }
                    }

                    // Delete options not in the keep list for this question
                    $question->options()->whereNotIn('id', $keepOptionIds)->delete();
                }
            }

            // Delete removed questions
            $training->testQuestions()->whereNotIn('id', $keepQuestionIds)->delete();
        });

        return redirect()->route('trainings.index');
    }

    /**
     * Delete training.
     */
    public function destroy(Request $request, Training $training): RedirectResponse
    {
        $user = $request->user();
        if (! $user->hasModuleAccess('trainings') || ! $user->hasChurchRole(['Admin', 'Manager'])) {
            abort(403, 'Unauthorized.');
        }

        if ($training->church_id !== $user->current_church_id) {
            abort(403, 'Unauthorized.');
        }

        $training->delete();

        return redirect()->back();
    }

    /**
     * Assign training to church users.
     */
    public function assign(Request $request, Training $training): RedirectResponse
    {
        $user = $request->user();
        if (! $user->hasModuleAccess('trainings') || ! $user->hasChurchRole(['Admin', 'Manager'])) {
            abort(403, 'Unauthorized.');
        }

        if ($training->church_id !== $user->current_church_id) {
            abort(403, 'Unauthorized.');
        }

        $validated = $request->validate([
            'user_ids' => ['required', 'array'],
            'user_ids.*' => ['required', 'integer', 'exists:users,id'],
            'due_at' => ['nullable', 'date'],
        ]);

        DB::transaction(function () use ($training, $user, $validated) {
            foreach ($validated['user_ids'] as $uid) {
                // Verify assigned user belongs to the active church
                if ($user->currentChurch->users()->where('users.id', $uid)->exists()) {
                    // Delete previous attempts for this training and user
                    TrainingAttempt::where('training_id', $training->id)
                        ->where('user_id', $uid)
                        ->delete();

                    TrainingAssignment::updateOrCreate(
                        ['training_id' => $training->id, 'user_id' => $uid],
                        [
                            'assigned_by' => $user->id,
                            'due_at' => $validated['due_at'] ?? null,
                            'status' => 'pending'
                        ]
                    );
                }
            }
        });

        return redirect()->back();
    }

    /**
     * Load slide player.
     */
    public function play(Request $request, Training $training): Response
    {
        $user = $request->user();
        if (! $user->hasModuleAccess('trainings')) {
            abort(403, 'Unauthorized.');
        }

        if ($training->church_id !== $user->current_church_id) {
            abort(403, 'Unauthorized.');
        }

        // Check if user is assigned or is Admin/Manager (who can preview/play anyway)
        $isAssigned = TrainingAssignment::where('training_id', $training->id)->where('user_id', $user->id)->exists();
        if (! $isAssigned && ! $user->hasChurchRole(['Admin', 'Manager'])) {
            abort(403, 'This training has not been assigned to you.');
        }

        // Load steps, questions and option content.
        // Critical Security: We strip the 'is_correct' attribute from question options to avoid client-side inspecting.
        $training->load(['steps']);
        
        $questions = $training->testQuestions()
            ->with(['options' => fn($q) => $q->select('id', 'question_id', 'option_text', 'image_path')])
            ->orderBy('sort_order')
            ->get();

        return Inertia::render('trainings/Player', [
            'training' => $training,
            'steps' => $training->steps,
            'questions' => $questions,
        ]);
    }

    /**
     * Score test submissions.
     */
    public function submit(Request $request, Training $training): RedirectResponse
    {
        $user = $request->user();
        if (! $user->hasModuleAccess('trainings')) {
            abort(403, 'Unauthorized.');
        }

        if ($training->church_id !== $user->current_church_id) {
            abort(403, 'Unauthorized.');
        }

        $assignment = TrainingAssignment::where('training_id', $training->id)
            ->where('user_id', $user->id)
            ->first();

        // Standard user must be assigned
        if (! $assignment && ! $user->hasChurchRole(['Admin', 'Manager'])) {
            abort(403, 'You are not assigned to this training.');
        }

        $validated = $request->validate([
            'answers' => [$training->has_test ? 'required' : 'nullable', 'array'], // question_id => array of option_ids or single option_id
        ]);

        $answers = $validated['answers'] ?? [];

        $score = 0;
        $totalQuestions = $training->testQuestions()->count();

        if ($totalQuestions > 0) {
            $correctQuestions = 0;

            foreach ($training->testQuestions as $question) {
                $correctOptionIds = $question->options()->where('is_correct', true)->pluck('id')->toArray();
                $submittedAnswers = $answers[$question->id] ?? [];

                if (! is_array($submittedAnswers)) {
                    $submittedAnswers = [$submittedAnswers];
                }

                // Cast options array elements to integer for accurate comparisons
                $submittedAnswers = array_map('intval', $submittedAnswers);
                $correctOptionIds = array_map('intval', $correctOptionIds);

                sort($submittedAnswers);
                sort($correctOptionIds);

                if ($submittedAnswers === $correctOptionIds && ! empty($correctOptionIds)) {
                    $correctQuestions++;
                }
            }

            $score = round(($correctQuestions / $totalQuestions) * 100);
        } else {
            // No questions means a 100% completion by default
            $score = 100;
        }

        $passed = $score >= $training->passing_score;

        DB::transaction(function () use ($training, $user, $assignment, $score, $passed, $answers) {
            TrainingAttempt::create([
                'training_id' => $training->id,
                'user_id' => $user->id,
                'assignment_id' => $assignment?->id,
                'score' => $score,
                'passed' => $passed,
                'answers_json' => $answers,
                'completed_at' => now(),
            ]);

            if ($assignment) {
                $assignment->update([
                    'status' => $passed ? 'completed' : 'failed',
                ]);
            }
        });

        // Flash parameters to display in frontend overlay
        return redirect()->route('trainings.index')->with('flash', [
            'type' => $passed ? 'success' : 'error',
            'message' => $passed 
                ? "Congratulations! You passed the training test with {$score}%!" 
                : "You scored {$score}%, which is below the required {$training->passing_score}%. Please try again.",
        ]);
    }
}
