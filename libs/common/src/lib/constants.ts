/**
 * Meta
 */
export const BUILD_TIME = new Date().toISOString();
export const BUILD_TIMESTAMP = Date.now();

/**
 * Utils
 */
export const IS_PROD = process.env.NODE_ENV === 'production';
export const IS_DEV = process.env.NODE_ENV === 'development';
export const IS_TEST = process.env.NODE_ENV === 'test';

/**
 * URLs
 */
export const HOME_URL = '/';
export const DASHBOARD_URL = 'dashboard';
export const CONTACT_URL = 'contact';

// Legal
export const TERMS_OF_SERVICE_URL = 'terms.show';
export const PRIVACY_POLICY_URL = 'policy.show';

// User profile
export const USER_PROFILE_URL = 'profile.show';
export const USER_PROFILE_INFORMATION_UPDATE_URL = 'user-profile-information.update';
export const USER_PROFILE_PASSWORD_UPDATE_URL = 'user-password.update';

// Communities
export const COMMUNITY_CREATE_URL = 'communities.create';

// FIXME add constants for all routes
//Route::post('/communities', [CommunityController::class, 'store'])->name('communities.store');
//Route::get('/communities/{community}', [CommunityController::class, 'show'])->name('communities.show');
//Route::put('/communities/{community}', [CommunityController::class, 'update'])->name('communities.update');
//Route::delete('/communities/{community}', [CommunityController::class, 'destroy'])->name('communities.destroy');
//Route::post('/communities/{community}/members', [CommunityMemberController::class, 'store'])->name('community-members.store');
//Route::put('/communities/{community}/members/{user}', [CommunityMemberController::class, 'update'])->name('community-members.update');
//Route::delete('/communities/{community}/members/{user}', [CommunityMemberController::class, 'destroy'])->name('community-members.destroy');
//Route::get('/community-invitations/{invitation}', [CommunityInvitationController::class, 'accept'])->middleware(['signed'])->name('community-invitations.accept');
//Route::delete('/community-invitations/{invitation}', [CommunityInvitationController::class, 'destroy'])->name('community-invitations.destroy');

// Authentication
export const LOGIN_URL = 'login';
export const REGISTER_URL = 'register';
export const LOGOUT_URL = 'logout';
export const PASSWORD_CONFIRM_URL = 'password.confirm';
// URL for sending password forgotten requests
export const EMAIL_PASSWORD_URL = 'password.email';
export const PASSWORD_CONFIRMATION_URL = 'password.confirmation';
export const PASSWORD_UPDATE_URL = 'password.update';
export const FORGOT_PASSWORD_URL = 'password.request';
// URL for sending email verification requests
export const EMAIL_VERIFICATION_URL = 'verification.send';
export const EMAIL_VERIFICATION_STATUS_LINK_SENT = 'verification-link-sent';
export const DESTROY_OTHER_BROWSER_SESSIONS_URL = 'other-browser-sessions.destroy';
export const DELETE_USER_URL = 'current-user.destroy';
/**
 * API
 */
export const API_BASE_PATH = '/api/v1';
