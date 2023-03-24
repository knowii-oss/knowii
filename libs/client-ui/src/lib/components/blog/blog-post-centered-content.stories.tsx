import type { ComponentStory, ComponentMeta } from '@storybook/react';
import { BlogPostCenteredContent } from './blog-post-centered-content';

const Story: ComponentMeta<typeof BlogPostCenteredContent> = {
  component: BlogPostCenteredContent,
  title: 'BlogPostCenteredContent',
};
export default Story;

const Template: ComponentStory<typeof BlogPostCenteredContent> = (args) => (
  <BlogPostCenteredContent {...args}>Centered foo</BlogPostCenteredContent>
);

export const Primary = Template.bind({});
Primary.args = {};
