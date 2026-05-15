export const composeFile = "infrastructure/docker/docker-compose.yml";

export function buildComposeUpArgs() {
  return ["up", "-d", "--wait", "postgres", "redis", "minio"];
}

export function buildComposeInvocation(useLegacyBinary, composeArgs) {
  if (useLegacyBinary) {
    return {
      command: "docker-compose",
      args: ["-f", composeFile, ...composeArgs],
    };
  }

  return {
    command: "docker",
    args: ["compose", "-f", composeFile, ...composeArgs],
  };
}
