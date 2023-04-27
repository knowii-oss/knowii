/*
  Warnings:

  - You are about to drop the column `family_name` on the `user_profiles` table. All the data in the column will be lost.
  - You are about to drop the column `given_name` on the `user_profiles` table. All the data in the column will be lost.

*/
-- DropIndex
DROP INDEX "public"."idx_user_profile_family_name";

-- DropIndex
DROP INDEX "public"."idx_user_profile_given_name";

-- AlterTable
ALTER TABLE "public"."user_profiles" DROP COLUMN "family_name",
DROP COLUMN "given_name",
ADD COLUMN     "name" TEXT NOT NULL DEFAULT '';

-- CreateIndex
CREATE INDEX "idx_user_profile_name" ON "public"."user_profiles"("name");
