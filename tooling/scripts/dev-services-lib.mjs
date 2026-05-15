export const bucketName = "cognify-dev";

export const serviceDefinitions = {
  postgres: {
    name: "cognify-postgres",
    image: "postgres:16-alpine",
    hostPort: 5433,
    containerPort: 5432,
    volume: "cognify-postgres-data:/var/lib/postgresql/data",
  },
  redis: {
    name: "cognify-redis",
    image: "redis:7-alpine",
    hostPort: 6381,
    containerPort: 6379,
    volume: "cognify-redis-data:/data",
  },
  minio: {
    name: "cognify-minio",
    image: "minio/minio:latest",
    apiPort: 9002,
    consolePort: 9003,
    volume: "cognify-minio-data:/data",
  },
};
