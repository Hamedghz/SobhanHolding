using System.Threading;
using SobhanNotificationHub.Data;using SobhanNotificationHub.Notifications;using SobhanNotificationHub.Services;using SobhanNotificationHub.Tray;using SobhanNotificationHub.UI;
namespace SobhanNotificationHub;
public partial class App : System.Windows.Application
{
    private Mutex? _mutex;private CancellationTokenSource? _shutdown;private TrayIconService? _tray;private PollingWorker? _polling;public static AppServices Services { get; private set; }=null!;
    protected override async void OnStartup(StartupEventArgs e){base.OnStartup(e);_mutex=new Mutex(true,"Local\\SobhanNotificationHub.SingleInstance",out var first);if(!first){Shutdown();return;}
        AppDomain.CurrentDomain.UnhandledException+=(s,a)=>LoggingService.Error("unhandled",a.ExceptionObject as Exception);DispatcherUnhandledException+=(s,a)=>{LoggingService.Error("ui_unhandled",a.Exception);a.Handled=true;};
        _shutdown=new();var settings=AppSettings.Load();var cache=new LocalCacheDb();await cache.InitializeAsync();var tokenStore=new AuthTokenStore();var api=new SobhanApiClient(settings,tokenStore);var router=new NotificationActionRouter(api,cache);var tray=new TrayIconService();var notifier=new WindowsNotificationService(router,tray);Services=new AppServices(settings,tokenStore,api,cache,router,notifier,tray);_tray=tray;tray.Initialize(Services);await notifier.InitializeAsync();
        if(!tokenStore.HasCredentials){new DeviceConnectWindow().Show();}else{await StartConnectedAsync();}LoggingService.Info("startup");}
    public async Task StartConnectedAsync(){if(_shutdown is null)return;try{var config=await Services.Api.GetClientConfigAsync(_shutdown.Token);Services.CurrentConfig=config;Services.Tray.SetConnected(config.User.DisplayName);_polling?.Dispose();_polling=new PollingWorker(Services,_shutdown.Token);_polling.Start();await Services.Api.CheckVersionAsync(_shutdown.Token);}catch(Exception ex){LoggingService.Error("connect",ex);Services.Tray.SetWarning();}}
    protected override void OnExit(ExitEventArgs e){_shutdown?.Cancel();_polling?.Dispose();Services?.Notifier.Dispose();_tray?.Dispose();_mutex?.Dispose();LoggingService.Info("shutdown");base.OnExit(e);}
}
public sealed record AppServices(AppSettings Settings,AuthTokenStore TokenStore,SobhanApiClient Api,LocalCacheDb Cache,NotificationActionRouter Router,WindowsNotificationService Notifier,TrayIconService Tray){public Models.ClientConfig? CurrentConfig{get;set;}}
