import { Footer } from "@/components/Footer";
import { Header } from "@/components/Header";
import { InfiniteCarousel } from "@/components/InfiniteCarousel";

export default function HomePage() {
  return (
    <main className="min-h-screen bg-slate-50">
      <Header />
      <InfiniteCarousel />
      <Footer />
    </main>
  );
}
