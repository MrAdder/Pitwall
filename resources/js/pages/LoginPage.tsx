import { useSearchParams } from 'react-router-dom';

const errorMessages: Record<string, string> = {
    sign_in_state_mismatch: 'The sign-in request could not be verified. Please start again.',
    sign_in_cancelled: 'Sign-in was cancelled.',
    sign_in_incomplete: 'Microsoft did not complete the sign-in. Please try again.',
    sign_in_failed: 'Sign-in with Microsoft could not be completed. Please try again.',
};

/**
 * Sign-in.
 *
 * Microsoft only: there is no local password, so there is no password to
 * phish, reset or leak. Whatever MFA and Conditional Access the administrator's
 * own tenant enforces applies here too, which is a stronger guarantee than
 * anything this application could implement itself.
 */
export function LoginPage() {
    const [params] = useSearchParams();
    const error = params.get('error');

    return (
        <div className="flex min-h-full items-center justify-center p-6">
            <div className="w-full max-w-sm">
                <img
                    src="/images/banner.png"
                    alt="PitWall IT Operations"
                    className="mb-8 w-full rounded-md"
                    width={2400}
                    height={600}
                />

                <h1 className="text-xl font-semibold text-content">Sign in</h1>
                <p className="mt-1 text-sm text-content-muted">
                    Use your Microsoft work or school account. Your organisation's multi-factor authentication and
                    Conditional Access policies apply.
                </p>

                {error && (
                    <p className="mt-4 rounded-md border border-danger/40 bg-danger/10 p-3 text-sm text-danger">
                        {errorMessages[error] ?? 'Sign-in could not be completed. Please try again.'}
                    </p>
                )}

                <a
                    href="/auth/microsoft/redirect"
                    className="mt-6 block rounded-md bg-brand px-4 py-2 text-center text-sm font-medium text-surface hover:bg-brand-strong"
                >
                    Continue with Microsoft
                </a>

                <p className="mt-6 text-xs text-content-muted">
                    Signing in identifies you to this platform. It does not by itself grant access to any Microsoft 365
                    tenant — that requires a tenant to be connected and your account to be a member of it.
                </p>
            </div>
        </div>
    );
}
