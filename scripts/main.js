import { ReadyHandler } from '/scripts/ReadyHandler.js';
import { RelativeTime } from '/scripts/RelativeTime.js';
import { ScrollToTop } from '/scripts/ScrollToTop.js';
import { sync_theme_color } from '/scripts/utils.js';
import { Strings } from '/scripts/Strings.js';
import '/scripts/dom.js';

// Before anything renders, so a twin can read its words synchronously. A
// renderer that had to await its own text would be async all the way up, and
// the words are one cached module rather than something worth restructuring
// the whole client around.
await Strings.load();

ReadyHandler.add(RelativeTime.init);
ReadyHandler.add(ScrollToTop.init);
ReadyHandler.add(sync_theme_color);

// The one module entry point: each twin is lazy-imported only when the object
// it drives is on the page, keyed on the same class selector the twin renders.
// Add a new twin by adding one such guard.

// The navigation is on every page that has any, and this only adds a spoken
// state to a menu that already works without it.
if (document.getElementById('NavToggle')) import('/scripts/NavMenu.js');

if (document.querySelector('[data-infinite-scroll]'))  import('/scripts/InfiniteScroller.js');
if (document.querySelector('.SearchInput'))            import('/scripts/Search.js');

if (document.querySelector('.LoginForm'))              import('/scripts/LoginForm.js');
if (document.querySelector('.LogoutForm'))             import('/scripts/LogoutForm.js');
if (document.querySelector('.SignupForm'))             import('/scripts/SignupForm.js');
if (document.querySelector('.PasswordChangeForm'))     import('/scripts/PasswordChangeForm.js');
if (document.querySelector('.EmailChangeForm'))        import('/scripts/EmailChangeForm.js');
if (document.querySelector('.PasswordResetRequestForm')) import('/scripts/PasswordResetRequestForm.js');
if (document.querySelector('.PasswordResetForm'))      import('/scripts/PasswordResetForm.js');
if (document.querySelector('.MailSettingsForm'))       import('/scripts/MailSettingsForm.js');
if (document.querySelector('.SiteInfoSettingsForm'))   import('/scripts/SiteInfoSettingsForm.js');
if (document.querySelector('.ThemeSelect'))            import('/scripts/ThemeSelect.js');
if (document.querySelector('.LanguagePrompt, .LanguageSelect')) import('/scripts/LanguagePrompt.js');
if (document.querySelector('.SignupForm'))             import('/scripts/UsernameValidation.js');
if (document.querySelector('.VerificationResendButton')) import('/scripts/VerificationResendButton.js');
