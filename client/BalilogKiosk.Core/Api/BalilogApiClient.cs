using System.Net.Http.Headers;
using System.Text;
using System.Text.Json;
using BalilogKiosk.Core.Models;
using BalilogKiosk.Core.Time;

namespace BalilogKiosk.Core.Api;

public sealed class ApiResult<T>
{
    public bool Ok { get; private init; }

    public T? Data { get; private init; }

    public string? ErrorCode { get; private init; }

    public string? ErrorMessage { get; private init; }

    public int HttpStatus { get; private init; }

    public static ApiResult<T> Success(T data, int status = 200) =>
        new() { Ok = true, Data = data, HttpStatus = status };

    public static ApiResult<T> Fail(string code, string message, int status = 0) =>
        new() { Ok = false, ErrorCode = code, ErrorMessage = message, HttpStatus = status };
}

/// <summary>
/// Klien REST API BALI-LOG (Laravel).
/// </summary>
public sealed class BalilogApiClient
{
    private static readonly JsonSerializerOptions JsonOptions = new(JsonSerializerDefaults.Web)
    {
        PropertyNamingPolicy = JsonNamingPolicy.SnakeCaseLower,
        PropertyNameCaseInsensitive = true,
    };

    private readonly HttpClient _http;
    private readonly ServerClock _clock;

    public BalilogApiClient(HttpClient httpClient, ServerClock clock, string baseUrl)
    {
        _http = httpClient;
        _clock = clock;
        _http.BaseAddress = new Uri(baseUrl.TrimEnd('/') + "/");
        _http.Timeout = TimeSpan.FromSeconds(30);
    }

    public string? DeviceToken { get; set; }

    public async Task<bool> HealthAsync(CancellationToken cancellationToken = default)
    {
        try
        {
            using var response = await _http.GetAsync("health", cancellationToken);

            return response.IsSuccessStatusCode;
        }
        catch (Exception)
        {
            return false;
        }
    }

    public Task<ApiResult<EnrollData>> EnrollAsync(EnrollRequest request, CancellationToken cancellationToken = default)
    {
        return SendAsync<EnrollData>(CreateRequest(HttpMethod.Post, "devices/enroll", request, authenticate: false), cancellationToken);
    }

    public Task<ApiResult<BootstrapData>> BootstrapAsync(CancellationToken cancellationToken = default)
    {
        return SendAsync<BootstrapData>(CreateRequest(HttpMethod.Get, "bootstrap"), cancellationToken);
    }

    public Task<ApiResult<CachedStudent>> LookupStudentAsync(string nisn, CancellationToken cancellationToken = default)
    {
        return SendAsync<CachedStudent>(CreateRequest(HttpMethod.Get, $"students/{Uri.EscapeDataString(nisn)}"), cancellationToken);
    }

    public Task<ApiResult<CachedStudent>> SetPinAsync(string nisn, string pin, CancellationToken cancellationToken = default)
    {
        return SendAsync<CachedStudent>(
            CreateRequest(HttpMethod.Post, $"students/{Uri.EscapeDataString(nisn)}/pin", new { pin }),
            cancellationToken);
    }

    public Task<ApiResult<SessionDto>> StartSessionAsync(StartSessionRequest request, CancellationToken cancellationToken = default)
    {
        return SendAsync<SessionDto>(CreateRequest(HttpMethod.Post, "sessions/start", request), cancellationToken);
    }

    public Task<ApiResult<HeartbeatData>> HeartbeatAsync(string sessionUuid, CancellationToken cancellationToken = default)
    {
        return SendAsync<HeartbeatData>(
            CreateRequest(HttpMethod.Post, "sessions/heartbeat", new { session_uuid = sessionUuid }),
            cancellationToken);
    }

    public Task<ApiResult<SessionDto>> EndSessionAsync(EndSessionRequest request, CancellationToken cancellationToken = default)
    {
        return SendAsync<SessionDto>(CreateRequest(HttpMethod.Post, "sessions/end", request), cancellationToken);
    }

    public Task<ApiResult<SyncResults>> SyncSessionsAsync(IEnumerable<SyncSessionItem> sessions, CancellationToken cancellationToken = default)
    {
        return SendAsync<SyncResults>(
            CreateRequest(HttpMethod.Post, "sync/sessions", new { sessions }),
            cancellationToken);
    }

    public Task<ApiResult<AppReleaseInfo>> GetLatestAppAsync(CancellationToken cancellationToken = default)
    {
        return SendAsync<AppReleaseInfo>(CreateRequest(HttpMethod.Get, "app/latest"), cancellationToken);
    }

    /// <summary>
    /// Mengunduh installer rilis ke path tujuan (streaming).
    /// URL boleh absolut (dari respons /app/latest) maupun relatif.
    /// </summary>
    public async Task<bool> DownloadInstallerAsync(string url, string destinationPath, CancellationToken cancellationToken = default)
    {
        try
        {
            using var response = await _http.SendAsync(
                CreateRequest(HttpMethod.Get, url),
                HttpCompletionOption.ResponseHeadersRead,
                cancellationToken);

            if (!response.IsSuccessStatusCode)
            {
                return false;
            }

            var directory = Path.GetDirectoryName(destinationPath);

            if (!string.IsNullOrEmpty(directory))
            {
                Directory.CreateDirectory(directory);
            }

            await using var target = File.Create(destinationPath);
            await response.Content.CopyToAsync(target, cancellationToken);

            return true;
        }
        catch (Exception)
        {
            return false;
        }
    }

    public async Task<ApiResult<ScreenshotData>> UploadScreenshotAsync(
        string sessionUuid,
        string filePath,
        string screenshotUuid,
        DateTimeOffset capturedAtClient,
        CancellationToken cancellationToken = default)
    {
        try
        {
            using var form = new MultipartFormDataContent();
            form.Add(new StringContent(screenshotUuid), "screenshot_uuid");
            form.Add(new StringContent(capturedAtClient.ToUniversalTime().ToString("o")), "captured_at_client");

            await using var stream = File.OpenRead(filePath);
            var streamContent = new StreamContent(stream);
            streamContent.Headers.ContentType = new MediaTypeHeaderValue(
                Path.GetExtension(filePath).Equals(".webp", StringComparison.OrdinalIgnoreCase)
                    ? "image/webp"
                    : "image/jpeg");

            form.Add(streamContent, "image_file", Path.GetFileName(filePath));

            using var request = CreateRequest(HttpMethod.Post, $"sessions/{sessionUuid}/screenshot");
            request.Content = form;

            return await SendAsync<ScreenshotData>(request, cancellationToken);
        }
        catch (IOException)
        {
            return ApiResult<ScreenshotData>.Fail("file_error", "Berkas screenshot tidak dapat dibaca.");
        }
        catch (UnauthorizedAccessException)
        {
            return ApiResult<ScreenshotData>.Fail("file_error", "Tidak memiliki akses ke berkas screenshot.");
        }
    }

    private HttpRequestMessage CreateRequest(HttpMethod method, string path, object? body = null, bool authenticate = true)
    {
        var request = new HttpRequestMessage(method, path);

        if (authenticate && !string.IsNullOrEmpty(DeviceToken))
        {
            request.Headers.Authorization = new AuthenticationHeaderValue("Bearer", DeviceToken);
        }

        if (body is not null)
        {
            request.Content = new StringContent(JsonSerializer.Serialize(body, JsonOptions), Encoding.UTF8, "application/json");
        }

        return request;
    }

    private async Task<ApiResult<T>> SendAsync<T>(HttpRequestMessage request, CancellationToken cancellationToken)
    {
        try
        {
            using (request)
            using (var response = await _http.SendAsync(request, cancellationToken))
            {
                var text = await response.Content.ReadAsStringAsync(cancellationToken);
                var status = (int)response.StatusCode;

                if (string.IsNullOrWhiteSpace(text))
                {
                    return ApiResult<T>.Fail("empty_response", "Respons server kosong.", status);
                }

                ApiEnvelope<T>? envelope = null;

                try
                {
                    envelope = JsonSerializer.Deserialize<ApiEnvelope<T>>(text, JsonOptions);
                }
                catch (JsonException)
                {
                    // ditangani di bawah: sertakan cuplikan respons untuk diagnosa
                }

                if (envelope is null)
                {
                    var snippet = text.Length > 200 ? text[..200] : text;

                    return ApiResult<T>.Fail("parse_error", $"Respons server tidak dapat dibaca (HTTP {status}). {snippet}", status);
                }

                _clock.Sync(envelope.ServerTime);

                if (envelope.Ok && envelope.Data is not null)
                {
                    return ApiResult<T>.Success(envelope.Data, status);
                }

                return ApiResult<T>.Fail(
                    envelope.Error?.Code ?? "error",
                    envelope.Error?.Message ?? "Terjadi kesalahan pada server.",
                    status);
            }
        }
        catch (TaskCanceledException)
        {
            return ApiResult<T>.Fail("timeout", "Koneksi ke server timeout.");
        }
        catch (HttpRequestException)
        {
            return ApiResult<T>.Fail("network", "Tidak dapat terhubung ke server.");
        }
        catch (JsonException)
        {
            return ApiResult<T>.Fail("parse_error", "Respons server tidak valid.");
        }
    }
}
