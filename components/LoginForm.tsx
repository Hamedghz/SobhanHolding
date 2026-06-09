"use client";

import { FormEvent, useState } from "react";

type FormErrors = {
  identity?: string;
  password?: string;
};

export function LoginForm() {
  const [identity, setIdentity] = useState("");
  const [password, setPassword] = useState("");
  const [errors, setErrors] = useState<FormErrors>({});
  const [isLoading, setIsLoading] = useState(false);

  function validate() {
    const nextErrors: FormErrors = {};

    if (!identity.trim()) {
      nextErrors.identity = "وارد کردن نام کاربری یا ایمیل الزامی است.";
    }

    if (!password) {
      nextErrors.password = "رمز عبور الزامی است.";
    } else if (password.length < 6) {
      nextErrors.password = "رمز عبور باید حداقل ۶ کاراکتر باشد.";
    }

    setErrors(nextErrors);
    return Object.keys(nextErrors).length === 0;
  }

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();

    if (!validate()) {
      return;
    }

    setIsLoading(true);
    await new Promise((resolve) => setTimeout(resolve, 700));
    setIsLoading(false);
  }

  return (
    <form
      onSubmit={handleSubmit}
      className="w-full max-w-[400px] rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"
      noValidate
    >
      <div className="mb-6 text-center">
        <h1 className="text-2xl font-bold text-slate-950">ورود به پنل سبحان</h1>
        <p className="mt-2 text-sm leading-6 text-slate-500">
          برای دسترسی به داشبورد، اطلاعات حساب خود را وارد کنید.
        </p>
      </div>

      <div className="space-y-4">
        <div>
          <label htmlFor="identity" className="mb-2 block text-sm font-medium text-slate-700">
            نام کاربری یا ایمیل
          </label>
          <input
            id="identity"
            value={identity}
            onChange={(event) => setIdentity(event.target.value)}
            className="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none transition focus:border-blue-600 focus:ring-4 focus:ring-blue-100"
            aria-invalid={Boolean(errors.identity)}
            aria-describedby={errors.identity ? "identity-error" : undefined}
          />
          {errors.identity ? (
            <p id="identity-error" className="mt-2 text-xs text-red-600">
              {errors.identity}
            </p>
          ) : null}
        </div>

        <div>
          <label htmlFor="password" className="mb-2 block text-sm font-medium text-slate-700">
            رمز عبور
          </label>
          <input
            id="password"
            type="password"
            value={password}
            onChange={(event) => setPassword(event.target.value)}
            className="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none transition focus:border-blue-600 focus:ring-4 focus:ring-blue-100"
            aria-invalid={Boolean(errors.password)}
            aria-describedby={errors.password ? "password-error" : undefined}
          />
          {errors.password ? (
            <p id="password-error" className="mt-2 text-xs text-red-600">
              {errors.password}
            </p>
          ) : null}
        </div>
      </div>

      <button
        type="submit"
        disabled={isLoading}
        className="mt-6 w-full rounded-xl bg-blue-600 px-4 py-3 text-sm font-medium text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
      >
        {isLoading ? "در حال ورود..." : "ورود به پنل"}
      </button>

      <a href="#" className="mt-4 block text-center text-sm text-blue-600 hover:text-blue-700">
        فراموشی رمز عبور؟
      </a>
    </form>
  );
}
