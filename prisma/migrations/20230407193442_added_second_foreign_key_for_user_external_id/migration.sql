/*
  Warnings:

  - You are about to drop the column `user_id` on the `users` table. All the data in the column will be lost.
  - A unique constraint covering the columns `[user_id_external]` on the table `user_profiles` will be added. If there are existing duplicate values, this will fail.
  - A unique constraint covering the columns `[user_id_external]` on the table `users` will be added. If there are existing duplicate values, this will fail.
  - Made the column `user_id` on table `user_profiles` required. This step will fail if there are existing NULL values in that column.

*/
-- DropForeignKey
ALTER TABLE "public"."user_profiles" DROP CONSTRAINT "user_profiles_user_id_fkey";

-- AlterTable
ALTER TABLE "public"."user_profiles" ADD COLUMN     "user_id_external" UUID,
ALTER COLUMN "user_id" SET NOT NULL;

-- AlterTable
ALTER TABLE "public"."users" DROP COLUMN "user_id",
ADD COLUMN     "user_id_external" UUID;

-- CreateIndex
CREATE UNIQUE INDEX "user_profiles_user_id_external_key" ON "public"."user_profiles"("user_id_external");

-- CreateIndex
CREATE INDEX "idx_user_profile_given_name" ON "public"."user_profiles"("given_name");

-- CreateIndex
CREATE INDEX "idx_user_profile_family_name" ON "public"."user_profiles"("family_name");

-- CreateIndex
CREATE UNIQUE INDEX "users_user_id_external_key" ON "public"."users"("user_id_external");

-- CreateIndex
CREATE INDEX "idx_user_username" ON "public"."users"("username");

-- CreateIndex
CREATE INDEX "idx_user_email" ON "public"."users"("email");

-- AddForeignKey
ALTER TABLE "public"."user_profiles" ADD CONSTRAINT "user_profiles_user_id_fkey" FOREIGN KEY ("user_id") REFERENCES "public"."users"("id") ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "public"."user_profiles" ADD CONSTRAINT "user_profiles_user_id_external_fkey" FOREIGN KEY ("user_id_external") REFERENCES "public"."users"("user_id_external") ON DELETE SET NULL ON UPDATE CASCADE;
