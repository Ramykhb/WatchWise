/** The `POST /auth/login` body; `remember` says whether to keep this device signed in. */
export const loginPayload = (email: string, password: string, remember: boolean) => ({ email, password, remember })
