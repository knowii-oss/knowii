/*
  Warnings:

  - You are about to drop the column `image_url` on the `user_profiles` table. All the data in the column will be lost.

*/
-- AlterTable
ALTER TABLE "public"."user_profiles" DROP COLUMN "image_url",
ADD COLUMN     "avatar_url" TEXT NOT NULL DEFAULT '';
