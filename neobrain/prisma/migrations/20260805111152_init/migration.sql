-- CreateEnum
CREATE TYPE "Role" AS ENUM ('visitor', 'client', 'admin');

-- CreateEnum
CREATE TYPE "ReleaseKind" AS ENUM ('announce', 'release', 'patch', 'news');

-- CreateTable
CREATE TABLE "users" (
    "id" TEXT NOT NULL,
    "email" TEXT NOT NULL,
    "passwordHash" TEXT NOT NULL,
    "displayName" TEXT,
    "role" "Role" NOT NULL DEFAULT 'visitor',
    "balanceRub" DECIMAL(12,2) NOT NULL DEFAULT 0,
    "monthlyQuota" INTEGER NOT NULL DEFAULT 50,
    "createdAt" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updatedAt" TIMESTAMP(3) NOT NULL,

    CONSTRAINT "users_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "projects" (
    "id" TEXT NOT NULL,
    "ownerId" TEXT NOT NULL,
    "name" TEXT NOT NULL,
    "repoUrl" TEXT,
    "domain" TEXT,
    "createdAt" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT "projects_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "token_logs" (
    "id" TEXT NOT NULL,
    "userId" TEXT,
    "projectId" TEXT,
    "taskType" TEXT NOT NULL,
    "model" TEXT NOT NULL,
    "promptTokens" INTEGER NOT NULL DEFAULT 0,
    "outputTokens" INTEGER NOT NULL DEFAULT 0,
    "costRub" DECIMAL(12,4) NOT NULL DEFAULT 0,
    "cached" BOOLEAN NOT NULL DEFAULT false,
    "createdAt" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT "token_logs_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "game_releases" (
    "id" TEXT NOT NULL,
    "gameSlug" TEXT NOT NULL,
    "kind" "ReleaseKind" NOT NULL DEFAULT 'news',
    "title" TEXT NOT NULL,
    "version" TEXT,
    "body" TEXT NOT NULL,
    "coverUrl" TEXT,
    "published" BOOLEAN NOT NULL DEFAULT false,
    "publishedAt" TIMESTAMP(3),
    "createdAt" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT "game_releases_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "pages_static" (
    "id" TEXT NOT NULL,
    "site" TEXT NOT NULL,
    "slug" TEXT NOT NULL,
    "title" TEXT NOT NULL,
    "html" TEXT NOT NULL,
    "updatedAt" TIMESTAMP(3) NOT NULL,

    CONSTRAINT "pages_static_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "sessions" (
    "id" TEXT NOT NULL,
    "userId" TEXT NOT NULL,
    "token" TEXT NOT NULL,
    "userAgent" TEXT,
    "ip" TEXT,
    "expiresAt" TIMESTAMP(3) NOT NULL,
    "createdAt" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT "sessions_pkey" PRIMARY KEY ("id")
);

-- CreateIndex
CREATE UNIQUE INDEX "users_email_key" ON "users"("email");

-- CreateIndex
CREATE INDEX "projects_ownerId_idx" ON "projects"("ownerId");

-- CreateIndex
CREATE INDEX "token_logs_userId_idx" ON "token_logs"("userId");

-- CreateIndex
CREATE INDEX "token_logs_projectId_idx" ON "token_logs"("projectId");

-- CreateIndex
CREATE INDEX "token_logs_createdAt_idx" ON "token_logs"("createdAt");

-- CreateIndex
CREATE INDEX "token_logs_model_idx" ON "token_logs"("model");

-- CreateIndex
CREATE INDEX "game_releases_gameSlug_idx" ON "game_releases"("gameSlug");

-- CreateIndex
CREATE INDEX "game_releases_published_publishedAt_idx" ON "game_releases"("published", "publishedAt");

-- CreateIndex
CREATE UNIQUE INDEX "pages_static_site_slug_key" ON "pages_static"("site", "slug");

-- CreateIndex
CREATE UNIQUE INDEX "sessions_token_key" ON "sessions"("token");

-- CreateIndex
CREATE INDEX "sessions_userId_idx" ON "sessions"("userId");

-- AddForeignKey
ALTER TABLE "projects" ADD CONSTRAINT "projects_ownerId_fkey" FOREIGN KEY ("ownerId") REFERENCES "users"("id") ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "token_logs" ADD CONSTRAINT "token_logs_userId_fkey" FOREIGN KEY ("userId") REFERENCES "users"("id") ON DELETE SET NULL ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "token_logs" ADD CONSTRAINT "token_logs_projectId_fkey" FOREIGN KEY ("projectId") REFERENCES "projects"("id") ON DELETE SET NULL ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "sessions" ADD CONSTRAINT "sessions_userId_fkey" FOREIGN KEY ("userId") REFERENCES "users"("id") ON DELETE CASCADE ON UPDATE CASCADE;
