import http from "node:http";
import net from "node:net";

const proxyHost = process.env.COGNIFY_PROXY_HOST ?? "127.0.0.1";
const proxyPort = Number(process.env.COGNIFY_PROXY_PORT ?? 3001);
const webHost = process.env.COGNIFY_WEB_HOST ?? "127.0.0.1";
const webPort = Number(process.env.COGNIFY_WEB_PORT ?? 8880);
const apiHost = process.env.COGNIFY_API_HOST ?? "127.0.0.1";
const apiPort = Number(process.env.COGNIFY_API_PORT ?? 8890);

function targetFor(path = "/") {
  if (path.startsWith("/api") || path.startsWith("/sanctum")) {
    return { host: apiHost, port: apiPort };
  }

  return { host: webHost, port: webPort };
}

const server = http.createServer((request, response) => {
  const target = targetFor(request.url);
  const headers = {
    ...request.headers,
    host: `${target.host}:${target.port}`,
    "x-forwarded-host": request.headers.host ?? "",
    "x-forwarded-proto": "http",
  };

  const upstream = http.request(
    {
      host: target.host,
      port: target.port,
      method: request.method,
      path: request.url,
      headers,
    },
    (upstreamResponse) => {
      response.writeHead(upstreamResponse.statusCode ?? 502, upstreamResponse.headers);
      upstreamResponse.pipe(response);
    },
  );

  upstream.on("error", (error) => {
    response.writeHead(502, { "content-type": "text/plain" });
    response.end(`Proxy error: ${error.message}`);
  });

  request.pipe(upstream);
});

server.on("upgrade", (request, socket, head) => {
  const target = targetFor(request.url);
  const upstream = net.connect(target.port, target.host, () => {
    upstream.write(`${request.method} ${request.url} HTTP/${request.httpVersion}\r\n`);

    const headers = {
      ...request.headers,
      host: `${target.host}:${target.port}`,
    };

    for (const [key, value] of Object.entries(headers)) {
      upstream.write(`${key}: ${value}\r\n`);
    }

    upstream.write("\r\n");

    if (head.length > 0) {
      upstream.write(head);
    }

    upstream.pipe(socket);
    socket.pipe(upstream);
  });

  upstream.on("error", () => socket.destroy());
});

server.listen(proxyPort, proxyHost, () => {
  console.log(`Cognify dev proxy: http://${proxyHost}:${proxyPort}`);
  console.log(`  web -> http://${webHost}:${webPort}`);
  console.log(`  api -> http://${apiHost}:${apiPort}`);
});
