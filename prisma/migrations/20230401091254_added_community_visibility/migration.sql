-- CreateEnum
CREATE TYPE "public"."community_visibility" AS ENUM ('PUBLIC', 'PRIVATE');

-- AlterTable
ALTER TABLE "public"."communities" ADD COLUMN     "visibility" "public"."community_visibility" NOT NULL DEFAULT 'PUBLIC';
