# Sobhan Messenger Realtime

سرویس مستقل Socket.IO برای typing، presence، receipt، live location و تحویل فوری پیام است. سایت PHP مرجع نهایی مجوز و ثبت پیام باقی می‌ماند.

```bash
cp .env.example .env
npm install
npm start
```

پشت reverse proxy دارای HTTPS اجرا شود. `REALTIME_SECRET` و `MESSENGER_INTERNAL_KEY` باید با تنظیمات محرمانه `chat_settings` یکسان باشند.
