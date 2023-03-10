import type { ComponentStory, ComponentMeta } from '@storybook/react';
import { LanguageSwitch } from './LanguageSwitch';

const Story: ComponentMeta<typeof LanguageSwitch> = {
  component: LanguageSwitch,
  title: 'LanguageSwitch',
};
export default Story;

const Template: ComponentStory<typeof LanguageSwitch> = (args) => <LanguageSwitch {...args} />;

export const Primary = Template.bind({});
Primary.args = {};
