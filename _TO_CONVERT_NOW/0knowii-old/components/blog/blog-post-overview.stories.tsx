import type { ComponentMeta, ComponentStory } from '@storybook/react';
import { BlogPostOverview } from './blog-post-overview';

const Story: ComponentMeta<typeof BlogPostOverview> = {
  component: BlogPostOverview,
  title: 'BlogPost',
};
export default Story;

const Template: ComponentStory<typeof BlogPostOverview> = (args) => <BlogPostOverview {...args} />;

export const Primary = Template.bind({});
Primary.args = {
  title: 'Foo Bar Baz',
  publishedOn: '2023-03-23',
  slug: 'foo-bar-baz',
  summary: 'Foo Bar Baz!',
  image: '/images/blog/blog-demo-cover.png',
};
