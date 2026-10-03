<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController as GuestHome;
use App\Http\Controllers\Guest\ApplicationController as GuestApplication;
use App\Http\Controllers\Guest\VacancyController as GuestVacancy;
use App\Http\Controllers\Guest\ApplicationReportController as GuestApplicationReport;
use App\Http\Controllers\Guest\RQAController as GuestRQA;
use App\Http\Controllers\Admin\HomeController as AdminHome;
use App\Http\Controllers\Admin\ApplicationController as AdminApplication;
use App\Http\Controllers\Admin\UserController as AdminUser;
use App\Http\Controllers\Admin\InquiryController as AdminInquiry;
use App\Http\Controllers\Admin\VacancyController as AdminVacancy;
use App\Http\Controllers\Admin\VacancyReportController as AdminVacancyReport;
use App\Http\Controllers\Admin\TrainingController as AdminTraining;
use App\Http\Controllers\Guest\OpenAIController;
use App\Http\Controllers\Admin\DiscrepancyController as AdminDiscrepancy;
use App\Http\Controllers\Admin\WrittenExamController as AdminWrittenExam;
use App\Http\Controllers\Admin\AssessmentGroupController as AdminAssessmentGroup;
use App\Http\Controllers\Admin\WrittenExamItemController as AdminWrittenExamItem;
use App\Http\Controllers\Admin\SkillTestController as AdminSkillTest;
use App\Http\Controllers\Admin\SkillTestGroupController as AdminSkillTestGroup;
use App\Http\Controllers\Admin\AssessmentCenterController as AdminAssessmentCenter;
use App\Http\Controllers\Admin\AssessmentContentBankController as AdminAssessmentContentBank;
use App\Http\Controllers\Admin\AssessmentPermissionController as AdminAssessmentPermission;
use App\Http\Controllers\Admin\AssessmentSnapshotController as AdminAssessmentSnapshot;
use App\Http\Controllers\Guest\SkillTestAttemptController as GuestSkillTestAttempt;


use Laravel\Socialite\Facades\Socialite;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Providers\RouteServiceProvider;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/auth/google', function () {
    return Socialite::driver('google')->redirect();
});

Route::get('/auth/google/callback', function () {
    $googleUser = Socialite::driver('google')->stateless()->user();

    if (!str_ends_with($googleUser->getEmail(), '@deped.gov.ph')) {
        return redirect('/login')->with('not_deped','Only DepEd emails are allowed.');
    }

    // Check if user already exists
    $user = User::where('email', $googleUser->getEmail())->first();

    if (!$user) {
        return redirect('/login')->with('not_reg', 'DepEd email is not registered.');
    }

    Auth::login($user);

    return redirect(session('url.intended', RouteServiceProvider::HOME));
});

// root route
Route::redirect('/', '/applications')->name('guest.index');

// not yet applied
Route::get('/vacancies', [GuestVacancy::class, 'index'])->name('guest.vacancies.index');
Route::get('/vacancies/{vacancy}', [GuestVacancy::class, 'show'])->name('guest.vacancies.show');
Route::get('/vacancies/{vacancy}/apply', [GuestVacancy::class, 'apply'])->name('guest.vacancies.apply');

Route::get('/applications', [GuestApplication::class, 'index'])->name('guest.applications.index');

Route::get('/reports', [GuestApplicationReport::class, 'index'])->name('guest.reports.index');
Route::get('/reports/{office}', [GuestApplicationReport::class, 'show'])->name('guest.reports.show');

Route::get('/rqas', [GuestRQA::class, 'index'])->name('guest.rqas.index');
Route::get('/rqas/{vacancy}', [GuestRQA::class, 'show'])->name('guest.rqas.show');

// already applied
Route::post('/applications/{vacancy}/apply', [GuestApplication::class, 'store'])->name('guest.applications.store');

Route::post('/applications', [GuestApplication::class, 'lookup'])->name('guest.applications.lookup');
Route::get('/applications/my', [GuestApplication::class, 'my'])->name('guest.applications.my');
Route::get('/applications/{application}', [GuestApplication::class, 'show'])->name('guest.applications.show');
Route::post('/applications/{application}/inquire', [GuestApplication::class, 'inquire'])->name('guest.applications.inquire');
Route::post('/applications/{application}/inquire2', [OpenAIController::class, 'inquire2'])->name('guest.applications.inquire2');
Route::post('/applications/{application}/assessment/{exam}/start', [\App\Http\Controllers\Guest\ExamAttemptController::class, 'start'])
    ->middleware('throttle:assessment-start')->name('guest.assessments.attempts.start');
Route::get('/assessments/attempts/{attempt}', [\App\Http\Controllers\Guest\ExamAttemptController::class, 'take'])
    ->middleware('throttle:assessment-take')->name('guest.assessments.attempts.take');
Route::get('/assessments/attempts/{attempt}/review', [\App\Http\Controllers\Guest\ExamAttemptController::class, 'reviewStatus'])
    ->middleware('throttle:assessment-review')->name('guest.assessments.attempts.review');
Route::post('/assessments/attempts/{attempt}/answer', [\App\Http\Controllers\Guest\ExamAttemptController::class, 'saveAnswer'])
    ->middleware('throttle:assessment-answer')->name('guest.assessments.attempts.answer');
Route::post('/assessments/attempts/{attempt}/submit', [\App\Http\Controllers\Guest\ExamAttemptController::class, 'submit'])
    ->middleware('throttle:assessment-submit')->name('guest.assessments.attempts.submit');
Route::post('/assessments/attempts/{attempt}/event', [\App\Http\Controllers\Guest\ExamAttemptController::class, 'eventLog'])
    ->middleware('throttle:assessment-event')->name('guest.assessments.attempts.event');
Route::post('/applications/{application}/skills/{skillTest}/start', [GuestSkillTestAttempt::class, 'start'])
    ->middleware('throttle:skill-start')->name('guest.skills.start');
Route::get('/skills/attempts/{attempt}', [GuestSkillTestAttempt::class, 'take'])
    ->middleware('throttle:skill-take')->name('guest.skills.attempts.take');
Route::post('/skills/attempts/{attempt}/inline', [GuestSkillTestAttempt::class, 'saveInline'])
    ->middleware('throttle:skill-save')->name('guest.skills.attempts.inline');
Route::post('/skills/attempts/{attempt}/upload', [GuestSkillTestAttempt::class, 'upload'])
    ->middleware('throttle:skill-upload')->name('guest.skills.attempts.upload');
Route::post('/skills/attempts/{attempt}/event', [GuestSkillTestAttempt::class, 'eventLog'])
    ->middleware('throttle:assessment-event')->name('guest.skills.attempts.event');
Route::post('/skills/attempts/{attempt}/submit', [GuestSkillTestAttempt::class, 'submit'])
    ->middleware('throttle:skill-submit')->name('guest.skills.attempts.submit');


// auth routes
Auth::routes(['register' => false]);

// user login
Route::group(['middleware' => ['active']], function () {
    Route::get('/admin', [AdminHome::class, 'index'])->name('admin.index');
    Route::get('/admin/get-notifications', [AdminHome::class, 'get_notifications'])->name('admin.get_notifications');
    Route::get('/admin/change_password', [AdminHome::class, 'change_password'])->name('admin.change_password');
    Route::put('/admin/change_password', [AdminHome::class, 'change_password_ok'])->name('admin.change_password_ok');

    Route::get('/admin/applications', [AdminApplication::class, 'index'])->name('admin.applications.index');
    Route::put('/admin/applications', [AdminApplication::class, 'search'])->name('admin.applications.search');
    Route::get('/admin/applications/create', [AdminApplication::class, 'create'])->name('admin.applications.create');
    Route::post('/admin/applications/', [AdminApplication::class, 'store'])->name('admin.applications.store');
    Route::post('/admin/applications/import', [AdminApplication::class, 'import'])->name('admin.applications.import');
    Route::get('/admin/applications/{application}', [AdminApplication::class, 'show'])->name('admin.applications.show');
    Route::get('/admin/applications/{application}/delete', [AdminApplication::class, 'delete'])->name('admin.applications.delete');
    Route::get('/admin/applications/{application}/edit', [AdminApplication::class, 'edit'])->name('admin.applications.edit');
    Route::get('/admin/applications/{application}/edit_scores', [AdminApplication::class, 'edit_scores'])->name('admin.applications.edit_scores');
    Route::get('/admin/applications/{application}/revert', [AdminApplication::class, 'revert'])->name('admin.applications.revert');
    Route::get('/admin/applications/{application}/remove_station', [AdminApplication::class, 'remove_station'])->name('admin.applications.remove_station');
    Route::get('/admin/applications/{application}/qualify', [AdminApplication::class, 'qualify'])->name('admin.applications.qualify');
    Route::get('/admin/applications/{application}/disqualify', [AdminApplication::class, 'disqualify'])->name('admin.applications.disqualify');
    Route::put('/admin/applications/{application}', [AdminApplication::class, 'update'])->name('admin.applications.update');
    Route::put('/admin/applications/{application}/update_scores/', [AdminApplication::class, 'update_scores'])->name('admin.applications.update_scores');
    Route::patch('/admin/applications/{application}', [AdminApplication::class, 'saveInquiry'])->name('admin.applications.saveInquiry');

    Route::get('/admin/applications/{vacancy}/show', [AdminApplication::class, 'vacancy_show'])->name('admin.applications.vacancy.show');
    Route::get('/admin/applications/{vacancy}/show/tagged', [AdminApplication::class, 'vacancy_show_tagged'])->name('admin.applications.vacancy.show.tagged');
    Route::get('/admin/applications/{vacancy}/show/carview', [AdminApplication::class, 'vacancy_show_carview'])->name('admin.applications.vacancy.show.carview');
    Route::get('/admin/applications/{vacancy}/show/carview2', [AdminApplication::class, 'vacancy_show_carview2'])->name('admin.applications.vacancy.show.carview2');
    Route::get('/admin/applications/{vacancy}/show/carview3', [AdminApplication::class, 'vacancy_show_carview3'])->name('admin.applications.vacancy.show.carview3');
    Route::get('/admin/applications/{vacancy}/show/carview4', [AdminApplication::class, 'vacancy_show_carview4'])->name('admin.applications.vacancy.show.carview4');
    Route::get('/admin/applications/{vacancy}/show/carview5', [AdminApplication::class, 'vacancy_show_carview5'])->name('admin.applications.vacancy.show.carview5');
    Route::get('/admin/applications/{vacancy}/show/careerview/{level}', [AdminApplication::class, 'vacancy_show_careerview'])->name('admin.applications.vacancy.show.careerview');
    Route::get('/admin/applications/{vacancy}/show/careerviewb/{level}', [AdminApplication::class, 'vacancy_show_careerviewb'])->name('admin.applications.vacancy.show.careerviewb');

    Route::get('/admin/vacancies/reports', [AdminVacancyReport::class, 'index'])->name('admin.vacancies.reports.index');
    Route::get('/admin/vacancies/reports/nonassessed', [AdminVacancyReport::class, 'nonassessed'])->name('admin.vacancies.reports.nonassessed');
    Route::get('/admin/vacancies/reports/list', [AdminVacancyReport::class, 'list'])->name('admin.vacancies.reports.list');
    Route::get('/admin/vacancies/reports/list/{application}/assess', [AdminVacancyReport::class, 'assess'])->name('admin.vacancies.reports.assess');
    Route::get('/admin/vacancies/reports/{office}', [AdminVacancyReport::class, 'show'])->name('admin.vacancies.reports.show');
    Route::get('/admin/vacancies/reports/{office}/{station}', [AdminVacancyReport::class, 'show_station'])->name('admin.vacancies.reports.show_station');

    Route::get('/admin/vacancies/active', [AdminVacancy::class, 'active'])->name('admin.vacancies.active');

    Route::get('/admin/vacancies', [AdminVacancy::class, 'index'])->name('admin.vacancies.index');
    Route::get('/admin/vacancies/create', [AdminVacancy::class, 'create'])->name('admin.vacancies.create');
    Route::post('/admin/vacancies', [AdminVacancy::class, 'store'])->name('admin.vacancies.store');
    Route::get('/admin/vacancies/{vacancy}', [AdminVacancy::class, 'edit'])->name('admin.vacancies.edit');
    Route::put('/admin/vacancies/{vacancy}', [AdminVacancy::class, 'update'])->name('admin.vacancies.update');
    Route::get('/admin/vacancies/{vacancy}/delete', [AdminVacancy::class, 'delete'])->name('admin.vacancies.delete');
    Route::get('/admin/vacancies/{vacancy}/apply', [AdminVacancy::class, 'apply'])->name('admin.vacancies.apply');

    Route::get('/admin/assessment-center', [AdminAssessmentCenter::class, 'index'])->name('admin.assessment_center.index');
    Route::get('/admin/assessment-snapshots', [AdminAssessmentSnapshot::class, 'index'])->name('admin.assessment_snapshots.index');
    Route::get('/admin/assessment-snapshots/{snapshot}/download', [AdminAssessmentSnapshot::class, 'download'])->name('admin.assessment_snapshots.download');

    Route::get('/admin/assessment-permissions', [AdminAssessmentPermission::class, 'index'])->name('admin.assessment_permissions.index');
    Route::put('/admin/assessment-permissions/{user}', [AdminAssessmentPermission::class, 'update'])->name('admin.assessment_permissions.update');

    Route::get('/admin/assessment-bank', [AdminAssessmentContentBank::class, 'index'])->name('admin.assessment_bank.index');
    Route::post('/admin/assessment-bank/{content}/retire', [AdminAssessmentContentBank::class, 'retire'])->name('admin.assessment_bank.retire');
    Route::post('/admin/assessment-bank/{content}/restore', [AdminAssessmentContentBank::class, 'restore'])->name('admin.assessment_bank.restore');

    Route::get('/admin/assessment-groups', [AdminAssessmentGroup::class, 'index'])->name('admin.assessment_groups.index');
    Route::get('/admin/assessment-groups/create', [AdminAssessmentGroup::class, 'create'])->name('admin.assessment_groups.create');
    Route::post('/admin/assessment-groups', [AdminAssessmentGroup::class, 'store'])->middleware('assessment.capability:author')->name('admin.assessment_groups.store');
    Route::get('/admin/assessment-groups/{assessmentGroup}/edit', [AdminAssessmentGroup::class, 'edit'])->name('admin.assessment_groups.edit');
    Route::get('/admin/assessment-groups/{assessmentGroup}/results', [AdminAssessmentGroup::class, 'results'])->name('admin.assessment_groups.results');
    Route::get('/admin/assessment-groups/{assessmentGroup}/analytics', [AdminAssessmentGroup::class, 'analytics'])->name('admin.assessment_groups.analytics');
    Route::get('/admin/assessment-groups/{assessmentGroup}/export', [AdminAssessmentGroup::class, 'exportCsv'])->name('admin.assessment_groups.export');
    Route::post('/admin/assessment-groups/{assessmentGroup}/equivalent-set', [AdminAssessmentGroup::class, 'createEquivalentSet'])->middleware('assessment.capability:author')->name('admin.assessment_groups.equivalent_set');
    Route::post('/admin/assessment-groups/{assessmentGroup}/accommodations', [AdminAssessmentGroup::class, 'saveAccommodation'])->middleware('assessment.capability:monitor')->name('admin.assessment_groups.accommodations');
    Route::post('/admin/assessment-groups/{assessmentGroup}/pause', [AdminAssessmentGroup::class, 'pause'])->middleware('assessment.capability:monitor')->name('admin.assessment_groups.pause');
    Route::post('/admin/assessment-groups/{assessmentGroup}/resume', [AdminAssessmentGroup::class, 'resume'])->middleware('assessment.capability:monitor')->name('admin.assessment_groups.resume');
    Route::post('/admin/assessment-groups/{assessmentGroup}/archive', [AdminAssessmentGroup::class, 'archive'])->middleware('assessment.capability:release')->name('admin.assessment_groups.archive');
    Route::post('/admin/assessment-groups/{assessmentGroup}/attempts/{attempt}/extend', [AdminAssessmentGroup::class, 'extendAttempt'])->middleware('assessment.capability:monitor')->name('admin.assessment_groups.attempts.extend');
    Route::post('/admin/assessment-groups/{assessmentGroup}/release-scores', [AdminAssessmentGroup::class, 'releaseScores'])->middleware('assessment.capability:release')->name('admin.assessment_groups.release_scores');
    Route::post('/admin/assessment-groups/{assessmentGroup}/hide-scores', [AdminAssessmentGroup::class, 'hideScores'])->middleware('assessment.capability:release')->name('admin.assessment_groups.hide_scores');
    Route::post('/admin/assessment-groups/{assessmentGroup}/incidents', [AdminAssessmentGroup::class, 'incident'])->name('admin.assessment_groups.incidents.store');
    Route::put('/admin/assessment-groups/{assessmentGroup}/incidents/{incident}/resolve', [AdminAssessmentGroup::class, 'resolveIncident'])->name('admin.assessment_groups.incidents.resolve');
    Route::post('/admin/assessment-groups/{assessmentGroup}/attempts/{attempt}/void-retake', [AdminAssessmentGroup::class, 'voidAndRetake'])->name('admin.assessment_groups.attempts.void_retake');
    Route::put('/admin/assessment-groups/{assessmentGroup}', [AdminAssessmentGroup::class, 'update'])->middleware('assessment.capability:author')->name('admin.assessment_groups.update');

    Route::get('/admin/assessment', [AdminWrittenExam::class, 'index'])->name('admin.assessments.index');
    Route::get('/admin/assessment/create', [AdminWrittenExam::class, 'create'])->name('admin.assessments.create');
    Route::post('/admin/assessment', [AdminWrittenExam::class, 'store'])->name('admin.assessments.store');
    Route::get('/admin/assessment/{exam}/edit', [AdminWrittenExam::class, 'edit'])->name('admin.assessments.edit');
    Route::get('/admin/assessment/{exam}/preview', [AdminWrittenExam::class, 'preview'])->name('admin.assessments.preview');
    Route::put('/admin/assessment/{exam}', [AdminWrittenExam::class, 'update'])->name('admin.assessments.update');
    Route::put('/admin/assessment/{exam}/toggle', [AdminWrittenExam::class, 'toggleStatus'])->middleware('assessment.capability:reviewer')->name('admin.assessments.toggle');
    Route::post('/admin/assessment/{exam}/approve', [AdminWrittenExam::class, 'approve'])->middleware('assessment.capability:reviewer')->name('admin.assessments.approve');
    Route::post('/admin/assessment/{exam}/pause', [AdminWrittenExam::class, 'pause'])->middleware('assessment.capability:monitor')->name('admin.assessments.pause');
    Route::post('/admin/assessment/{exam}/resume', [AdminWrittenExam::class, 'resume'])->middleware('assessment.capability:monitor')->name('admin.assessments.resume');
    Route::post('/admin/assessment/{exam}/archive', [AdminWrittenExam::class, 'archive'])->middleware('assessment.capability:release')->name('admin.assessments.archive');
    Route::post('/admin/assessment/{exam}/duplicate', [AdminWrittenExam::class, 'duplicate'])->name('admin.assessments.duplicate');
    Route::post('/admin/assessment/{exam}/ai-generate', [AdminWrittenExam::class, 'generateAi'])->middleware('assessment.capability:author')->name('admin.assessments.ai_generate');
    Route::post('/admin/assessment/{exam}/assign', [AdminWrittenExam::class, 'assignApplicants'])->name('admin.assessments.assign');
    Route::delete('/admin/assessment/{exam}', [AdminWrittenExam::class, 'destroy'])->name('admin.assessments.destroy');
    Route::get('/admin/assessment/{exam}/results', [AdminWrittenExam::class, 'results'])->name('admin.assessments.results');
    Route::delete('/admin/assessment/{exam}/attempts/{attempt}', [AdminWrittenExam::class, 'destroyAttempt'])->name('admin.assessments.attempts.destroy');
    Route::get('/admin/assessment/{exam}/items', [AdminWrittenExamItem::class, 'index'])->name('admin.assessments.items.index');
    Route::get('/admin/assessment/{exam}/items/generation-status', [AdminWrittenExamItem::class, 'generationStatus'])->name('admin.assessments.items.generation_status');
    Route::get('/admin/assessment/{exam}/items/create', [AdminWrittenExamItem::class, 'create'])->name('admin.assessments.items.create');
    Route::post('/admin/assessment/{exam}/items', [AdminWrittenExamItem::class, 'store'])->name('admin.assessments.items.store');
    Route::post('/admin/assessment/{exam}/items/import', [AdminWrittenExamItem::class, 'import'])->name('admin.assessments.items.import');
    Route::get('/admin/assessment/{exam}/items/{item}/edit', [AdminWrittenExamItem::class, 'edit'])->name('admin.assessments.items.edit');
    Route::put('/admin/assessment/{exam}/items/{item}', [AdminWrittenExamItem::class, 'update'])->name('admin.assessments.items.update');
    Route::put('/admin/assessment/{exam}/items/{item}/toggle', [AdminWrittenExamItem::class, 'toggleStatus'])->name('admin.assessments.items.toggle');
    Route::put('/admin/assessment/{exam}/items/{item}/review', [AdminWrittenExamItem::class, 'review'])->middleware('assessment.capability:reviewer')->name('admin.assessments.items.review');
    Route::delete('/admin/assessment/{exam}/items/{item}', [AdminWrittenExamItem::class, 'destroy'])->name('admin.assessments.items.destroy');
    Route::post('/admin/applications/{application}/assessment/{exam}/start', [\App\Http\Controllers\Admin\ExamAttemptController::class, 'start'])->name('admin.assessments.attempts.start');
    Route::get('/admin/assessments/attempts/{attempt}', [\App\Http\Controllers\Admin\ExamAttemptController::class, 'take'])->name('admin.assessments.attempts.take');
    Route::post('/admin/assessments/attempts/{attempt}/answer', [\App\Http\Controllers\Admin\ExamAttemptController::class, 'saveAnswer'])->name('admin.assessments.attempts.answer');
    Route::post('/admin/assessments/attempts/{attempt}/submit', [\App\Http\Controllers\Admin\ExamAttemptController::class, 'submit'])->name('admin.assessments.attempts.submit');

    Route::get('/admin/skill-groups', [AdminSkillTestGroup::class, 'index'])->name('admin.skill_groups.index');
    Route::get('/admin/skill-groups/create', [AdminSkillTestGroup::class, 'create'])->name('admin.skill_groups.create');
    Route::post('/admin/skill-groups', [AdminSkillTestGroup::class, 'store'])->middleware('assessment.capability:author')->name('admin.skill_groups.store');
    Route::get('/admin/skill-groups/{skillTestGroup}/edit', [AdminSkillTestGroup::class, 'edit'])->name('admin.skill_groups.edit');
    Route::put('/admin/skill-groups/{skillTestGroup}', [AdminSkillTestGroup::class, 'update'])->middleware('assessment.capability:author')->name('admin.skill_groups.update');
    Route::post('/admin/skill-groups/{skillTestGroup}/equivalent-set', [AdminSkillTestGroup::class, 'addEquivalentSet'])->middleware('assessment.capability:author')->name('admin.skill_groups.equivalent_set');
    Route::post('/admin/skill-groups/{skillTestGroup}/release-scores', [AdminSkillTestGroup::class, 'releaseScores'])->middleware('assessment.capability:release')->name('admin.skill_groups.release_scores');
    Route::post('/admin/skill-groups/{skillTestGroup}/hide-scores', [AdminSkillTestGroup::class, 'hideScores'])->middleware('assessment.capability:release')->name('admin.skill_groups.hide_scores');
    Route::post('/admin/skill-groups/{skillTestGroup}/pause', [AdminSkillTestGroup::class, 'pause'])->middleware('assessment.capability:monitor')->name('admin.skill_groups.pause');
    Route::post('/admin/skill-groups/{skillTestGroup}/resume', [AdminSkillTestGroup::class, 'resume'])->middleware('assessment.capability:monitor')->name('admin.skill_groups.resume');
    Route::post('/admin/skill-groups/{skillTestGroup}/archive', [AdminSkillTestGroup::class, 'archive'])->middleware('assessment.capability:release')->name('admin.skill_groups.archive');

    Route::get('/admin/skills', [AdminSkillTest::class, 'index'])->name('admin.skills.index');
    Route::get('/admin/skills/create', [AdminSkillTest::class, 'create'])->name('admin.skills.create');
    Route::post('/admin/skills/ai-draft', [AdminSkillTest::class, 'generateCreateDraft'])->middleware('assessment.capability:author')->name('admin.skills.ai_draft');
    Route::post('/admin/skills', [AdminSkillTest::class, 'store'])->middleware('assessment.capability:author')->name('admin.skills.store');
    Route::get('/admin/skills/{skillTest}/edit', [AdminSkillTest::class, 'edit'])->name('admin.skills.edit');
    Route::get('/admin/skills/{skillTest}/preview', [AdminSkillTest::class, 'preview'])->name('admin.skills.preview');
    Route::put('/admin/skills/{skillTest}', [AdminSkillTest::class, 'update'])->middleware('assessment.capability:author')->name('admin.skills.update');
    Route::post('/admin/skills/{skillTest}/revision', [AdminSkillTest::class, 'createRevision'])->name('admin.skills.revision');
    Route::post('/admin/skills/{skillTest}/ai-generate', [AdminSkillTest::class, 'generateAi'])->middleware('assessment.capability:author')->name('admin.skills.ai_generate');
    Route::put('/admin/skills/{skillTest}/review-task', [AdminSkillTest::class, 'reviewTask'])->middleware('assessment.capability:reviewer')->name('admin.skills.review_task');
    Route::post('/admin/skills/{skillTest}/rubric', [AdminSkillTest::class, 'saveRubric'])->name('admin.skills.rubric');
    Route::put('/admin/skills/{skillTest}/rubric/{criterion}/review', [AdminSkillTest::class, 'reviewCriterion'])->middleware('assessment.capability:reviewer')->name('admin.skills.rubric.review');
    Route::post('/admin/skills/{skillTest}/assign', [AdminSkillTest::class, 'assignApplicants'])->name('admin.skills.assign');
    Route::post('/admin/skills/{skillTest}/toggle', [AdminSkillTest::class, 'toggleStatus'])->middleware('assessment.capability:reviewer')->name('admin.skills.toggle');
    Route::post('/admin/skills/{skillTest}/approve', [AdminSkillTest::class, 'approve'])->middleware('assessment.capability:reviewer')->name('admin.skills.approve');
    Route::post('/admin/skills/{skillTest}/accommodations', [AdminSkillTest::class, 'saveAccommodation'])->middleware('assessment.capability:monitor')->name('admin.skills.accommodations');
    Route::post('/admin/skills/{skillTest}/pause', [AdminSkillTest::class, 'pause'])->middleware('assessment.capability:monitor')->name('admin.skills.pause');
    Route::post('/admin/skills/{skillTest}/resume', [AdminSkillTest::class, 'resume'])->middleware('assessment.capability:monitor')->name('admin.skills.resume');
    Route::post('/admin/skills/{skillTest}/archive', [AdminSkillTest::class, 'archive'])->middleware('assessment.capability:release')->name('admin.skills.archive');
    Route::post('/admin/skills/{skillTest}/attempts/{attempt}/extend', [AdminSkillTest::class, 'extendAttempt'])->middleware('assessment.capability:monitor')->name('admin.skills.attempts.extend');
    Route::get('/admin/skills/{skillTest}/results', [AdminSkillTest::class, 'results'])->name('admin.skills.results');
    Route::get('/admin/skills/{skillTest}/export', [AdminSkillTest::class, 'exportCsv'])->name('admin.skills.export');
    Route::get('/admin/skills/{skillTest}/submissions/{submission}/download', [AdminSkillTest::class, 'downloadSubmission'])->name('admin.skills.submissions.download');
    Route::post('/admin/skills/{skillTest}/approve-ai-scores', [AdminSkillTest::class, 'approveAiScores'])->middleware('assessment.capability:evaluator')->name('admin.skills.approve_ai_scores');
    Route::post('/admin/skills/{skillTest}/release-scores', [AdminSkillTest::class, 'releaseScores'])->middleware('assessment.capability:release')->name('admin.skills.release_scores');
    Route::post('/admin/skills/{skillTest}/hide-scores', [AdminSkillTest::class, 'hideScores'])->middleware('assessment.capability:release')->name('admin.skills.hide_scores');
    Route::post('/admin/skills/{skillTest}/incidents', [AdminSkillTest::class, 'incident'])->name('admin.skills.incidents.store');
    Route::put('/admin/skills/{skillTest}/incidents/{incident}/resolve', [AdminSkillTest::class, 'resolveIncident'])->name('admin.skills.incidents.resolve');
    Route::post('/admin/skills/{skillTest}/attempts/{attempt}/void-retake', [AdminSkillTest::class, 'voidAndRetake'])->name('admin.skills.attempts.void_retake');
    Route::post('/admin/skills/{skillTest}/attempts/{attempt}/final-score', [AdminSkillTest::class, 'finalizeScore'])->middleware('assessment.capability:evaluator')->name('admin.skills.final_score');

    Route::get('/admin/inquiries', [AdminInquiry::class, 'index'])->name('admin.inquiries.index');
});

Route::group(['middleware' => ['admin']], function () {
    Route::delete('/admin/applications/{application}', [AdminApplication::class, 'destroy'])->name('admin.applications.destroy');
    Route::delete('/admin/vacancies/{vacancy}', [AdminVacancy::class, 'destroy'])->name('admin.vacancies.destroy');

    Route::get('/admin/users', [AdminUser::class, 'index'])->name('admin.users.index');
    Route::get('/admin/users/create', [AdminUser::class, 'create'])->name('admin.users.create');
    Route::post('/admin/users', [AdminUser::class, 'store'])->name('admin.users.store');
    Route::get('/admin/users/{user}/delete', [AdminUser::class, 'delete'])->name('admin.users.delete');
    Route::get('/admin/users/{user}/reset', [AdminUser::class, 'reset'])->name('admin.users.reset');
    Route::put('/admin/users/{user}/reset', [AdminUser::class, 'resetOk'])->name('admin.users.resetOk');
    Route::delete('/admin/users/{user}', [AdminUser::class, 'destroy'])->name('admin.users.destroy');
    Route::get('/admin/users/{user}/edit', [AdminUser::class, 'edit'])->name('admin.users.edit');
    Route::put('/admin/users/{user}', [AdminUser::class, 'update'])->name('admin.users.update');

    Route::get('/admin/ai/', [AdminTraining::class, 'index'])->name('admin.ai.index');
    Route::get('/admin/ai/train', [AdminTraining::class, 'train'])->name('admin.ai.train');
    Route::post('/admin/ai/train/update_model', [AdminTraining::class, 'update_model'])->name('admin.ai.train.update_model');
    Route::get('/admin/ai/train/start', [AdminTraining::class, 'start'])->name('admin.ai.train.start');
    Route::get('/admin/ai/create', [AdminTraining::class, 'create'])->name('admin.ai.create');
    Route::post('/admin/ai/create', [AdminTraining::class, 'store'])->name('admin.ai.store');
    Route::get('/admin/ai/{training}/modify', [AdminTraining::class, 'modify'])->name('admin.ai.modify');
    Route::post('/admin/ai/{training}/modify', [AdminTraining::class, 'update'])->name('admin.ai.update');
    Route::get('/admin/ai/{training}/delete', [AdminTraining::class, 'delete'])->name('admin.ai.delete');

    Route::get('/admin/discrepancies/', [AdminDiscrepancy::class, 'index'])->name('admin.discrepancies.index');
    Route::get('/admin/discrepancies/{assessment}', [AdminDiscrepancy::class, 'modify'])->name('admin.discrepancies.modify');
    Route::put('/admin/discrepancies/{assessment}', [AdminDiscrepancy::class, 'update'])->name('admin.discrepancies.update');

});
