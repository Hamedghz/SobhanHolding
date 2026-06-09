import Link from "next/link";
import { LoginForm } from "@/components/LoginForm";

export default function LoginPage() {
  return (
    <main className="flex min-h-screen items-center justify-center bg-slate-50 px-4 py-10">
      <div className="w-full">
        <Link
          href="/"
          className="mx-auto mb-6 block w-fit text-sm font-medium text-slate-600 transition hover:text-slate-950"
        >
          شرکت پخش سبحان
        </Link>
        <LoginForm />
      </div>
    </main>
  );
}
