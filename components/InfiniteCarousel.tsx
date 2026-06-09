import { carouselItems } from "@/data/carousel";
import { CarouselCard } from "./CarouselCard";

export function InfiniteCarousel() {
  const repeatedItems = [...carouselItems, ...carouselItems];

  return (
    <section className="pause-on-hover overflow-hidden py-8 sm:py-12">
      <div className="mb-6 px-4 text-center sm:px-6">
        <h1 className="text-3xl font-bold leading-10 text-slate-950 sm:text-4xl">
          شرکت پخش سبحان
        </h1>
        <p className="mx-auto mt-3 max-w-2xl text-sm leading-7 text-slate-600 sm:text-base">
          سامانه ای ساده برای مدیریت پخش، ارزیابی عملکرد و دسترسی سریع به اطلاعات سازمانی.
        </p>
      </div>
      <div className="flex w-max gap-5 px-4 animate-rtl-carousel sm:gap-6 sm:px-6">
        {repeatedItems.map((item, index) => (
          <CarouselCard key={`${item.title}-${index}`} item={item} />
        ))}
      </div>
    </section>
  );
}
