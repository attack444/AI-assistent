-- CreateEnum
CREATE TYPE "GameStatus" AS ENUM ('soon', 'dev', 'released');

-- CreateTable
CREATE TABLE "games" (
    "id" TEXT NOT NULL,
    "slug" TEXT NOT NULL,
    "title" TEXT NOT NULL,
    "tagline" TEXT,
    "description" TEXT NOT NULL DEFAULT '',
    "coverUrl" TEXT,
    "playUrl" TEXT,
    "status" "GameStatus" NOT NULL DEFAULT 'dev',
    "featured" BOOLEAN NOT NULL DEFAULT false,
    "accent" TEXT,
    "createdAt" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updatedAt" TIMESTAMP(3) NOT NULL,

    CONSTRAINT "games_pkey" PRIMARY KEY ("id")
);

-- CreateIndex
CREATE UNIQUE INDEX "games_slug_key" ON "games"("slug");

-- CreateIndex
CREATE INDEX "games_status_idx" ON "games"("status");

-- CreateIndex
CREATE INDEX "games_featured_idx" ON "games"("featured");
