export const localPortDefaults = {
  api: 8890,
  db: 5433,
  minioApi: 9002,
  minioConsole: 9003,
  redis: 6381,
  web: 8880,
};

export function buildDevResetPlan() {
  return {
    steps: [
      {
        id: "services",
        command: "pnpm",
        args: ["dev:services"],
      },
      {
        id: "migrate",
        command: "php",
        args: ["artisan", "migrate:fresh", "--seed"],
        cwd: "apps/api",
      },
      {
        id: "api",
        command: "composer",
        args: ["run", "dev"],
        cwd: "apps/api",
      },
      {
        id: "web",
        command: "pnpm",
        args: ["--filter", "@cognify/web", "dev"],
      },
    ],
  };
}
