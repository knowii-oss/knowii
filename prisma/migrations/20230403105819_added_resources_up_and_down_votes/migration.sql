-- AlterTable
ALTER TABLE "public"."resources" ADD COLUMN     "down_votes" INTEGER NOT NULL DEFAULT 0,
ADD COLUMN     "up_votes" INTEGER NOT NULL DEFAULT 0;
